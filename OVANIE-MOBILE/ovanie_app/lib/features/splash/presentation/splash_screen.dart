import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_runtime_state.dart';
import '../../auth/data/auth_repository.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../cart/data/cart_api_repository.dart';
import '../../cart/domain/cart_store.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../shell/presentation/main_shell.dart';

/// Initialisation réelle de l'application.
///
/// Ordre imposé :
/// 1. restaurer session + panier locaux ;
/// 2. vérifier que Laravel est joignable ;
/// 3. si un token existe, le valider avec /me ;
/// 4. synchroniser le panier avec le même panier Laravel que le Web ;
/// 5. seulement ensuite afficher l'accueil.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _booting = false;
  bool _waitingForNetwork = false;
  bool _completed = false;
  int _lastConnectivityRevision = 0;
  String _step = 'Initialisation d’OVANIE…';
  double _progress = .1;

  /// Message affiché dans la carte quand le démarrage n'a pas pu aboutir.
  String? _problem;

  @override
  void initState() {
    super.initState();
    _lastConnectivityRevision = ApiRuntimeState.instance.connectivityRevision;
    ApiRuntimeState.instance.addListener(_onRuntimeStateChanged);
    unawaited(_bootstrap());
  }

  @override
  void dispose() {
    ApiRuntimeState.instance.removeListener(_onRuntimeStateChanged);
    super.dispose();
  }

  void _onRuntimeStateChanged() {
    final runtime = ApiRuntimeState.instance;
    final revision = runtime.connectivityRevision;
    if (_waitingForNetwork && !runtime.isOffline && revision != _lastConnectivityRevision) {
      _lastConnectivityRevision = revision;
      unawaited(_bootstrap());
    }
  }

  Future<void> _bootstrap() async {
    if (_booting || _completed) return;
    _booting = true;
    _waitingForNetwork = false;
    if (mounted) setState(() => _problem = null);

    try {
      _setStep('Restauration de votre session…', 0.2);
      await SessionStore.instance.restore();

      _setStep('Restauration de votre panier…', 0.4);
      await CartStore.instance.restore();

      _setStep('Connexion à OVANIE…', 0.6);
      await const AuthRepository().checkServer();

      if (SessionStore.instance.isAuthenticated) {
        _setStep('Vérification sécurisée de votre session…', 0.8);
        try {
          final user = await const AuthRepository().me();
          await SessionStore.instance.updateUser(user);
        } catch (error) {
          if (error is OvanieApiException && error.statusCode == 401) {
            await SessionStore.instance.expireFromServer();
            // L'intercepteur a déjà déclaré sessionExpired. On continue vers
            // MainShell ; l'overlay de reconnexion conservera ce contexte.
          } else {
            rethrow;
          }
        }
      }

      if (SessionStore.instance.isAuthenticated) {
        _setStep('Préparation de votre panier…', 0.92);
        await const CartApiRepository().refreshLocalCartFromServer();

        // Les favoris ne bloquent pas l'ouverture de l'accueil mais sont
        // rafraîchis dès que la session vient d'être confirmée.
        unawaited(FavoritesStore.instance.refreshFromServer());
      }

      if (!mounted) return;
      _completed = true;
      ApiRuntimeState.instance.markBootCompleted();
      Navigator.of(context).pushReplacement(MaterialPageRoute<void>(builder: (_) => const MainShell()));
    } catch (error) {
      if (_isNetworkFailure(error)) {
        _waitingForNetwork = true;
        ApiRuntimeState.instance.reportOffline(ApiClient.friendlyError(error));
        if (mounted) {
          setState(() => _problem = 'Impossible de joindre OVANIE.\nVérifiez votre connexion internet puis réessayez.');
        }
      } else if (error is OvanieApiException && error.statusCode == 401) {
        await SessionStore.instance.expireFromServer();
        if (!mounted) return;
        _completed = true;
        ApiRuntimeState.instance.markBootCompleted();
        Navigator.of(context).pushReplacement(MaterialPageRoute<void>(builder: (_) => const MainShell()));
      } else {
        // Une réponse serveur non réseau est affichée sous forme d'état propre
        // dans le Splash, avec possibilité de relancer l'initialisation.
        if (mounted) {
          setState(() => _problem = ApiClient.friendlyError(error));
        }
      }
    } finally {
      _booting = false;
    }
  }

  bool _isNetworkFailure(Object error) {
    final message = ApiClient.friendlyError(error).toLowerCase();
    return message.contains('connexion internet') ||
        message.contains('impossible de se connecter à ovanie') ||
        message.contains('impossible de contacter ovanie');
  }

  void _setStep(String value, double progress) {
    if (!mounted) return;
    setState(() {
      _step = value;
      _progress = progress;
    });
  }

  /// Bouton « Réessayer » de la carte : relance tout le démarrage.
  Future<void> _retry() async {
    if (_booting) return;
    if (ApiRuntimeState.instance.isOffline) {
      _waitingForNetwork = false;
      ApiRuntimeState.instance.reportOnline();
    }
    await _bootstrap();
  }

  @override
  Widget build(BuildContext context) {
    final problem = _problem;
    return OvanieAuthTheme(
      lightStatusBar: true,
      child: Scaffold(
        backgroundColor: const Color(0xFF0A2A7A),
        body: LayoutBuilder(
          builder: (context, box) {
            final w = box.maxWidth, h = box.maxHeight;
            final logoWidth = (w * .64).clamp(0.0, 360.0);
            return Stack(
              children: [
                Positioned.fill(
                  child: Image.asset(
                    'assets/images/auth/splash_bg.jpg',
                    fit: BoxFit.cover,
                    alignment: Alignment.bottomCenter,
                    excludeFromSemantics: true,
                  ),
                ),
                // Le logo remonte quand la carte d'erreur prend sa place.
                AnimatedPositioned(
                  duration: const Duration(milliseconds: 260),
                  curve: Curves.easeOut,
                  top: h * (problem == null ? .225 : .165),
                  left: 0,
                  right: 0,
                  child: Center(child: OvanieLogo(white: true, width: logoWidth)),
                ),
                if (problem == null)
                  Positioned(
                    top: h * .225 + logoWidth * .25 + h * .055,
                    left: 0,
                    right: 0,
                    child: Column(
                      children: [
                        _ProgressBar(value: _progress, width: (w * .585).clamp(0.0, 330.0)),
                        const SizedBox(height: 16),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 28),
                          child: Text(
                            _step,
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w500),
                          ),
                        ),
                      ],
                    ),
                  )
                else
                  Positioned(
                    top: h * .165 + logoWidth * .25 + h * .04,
                    left: 0,
                    right: 0,
                    child: Center(
                      child: Container(
                        width: (w * .61).clamp(220.0, 340.0),
                        padding: const EdgeInsets.fromLTRB(12, 16, 12, 12),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: const [BoxShadow(color: Color(0x33021338), blurRadius: 24, offset: Offset(0, 10))],
                        ),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Icon(Icons.cloud_off_outlined, color: authOrange, size: 44),
                            const SizedBox(height: 10),
                            Text(
                              problem,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: authNavy,
                                fontSize: 13.5,
                                height: 1.4,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const SizedBox(height: 14),
                            OvaniePrimaryButton(label: 'Réessayer', height: 42, loading: _booting, onPressed: _retry),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

/// Barre d'avancement du démarrage : orange puis blanc sur une piste translucide.
class _ProgressBar extends StatelessWidget {
  const _ProgressBar({required this.value, required this.width});
  final double value;
  final double width;

  @override
  Widget build(BuildContext context) => Semantics(
    label: 'Chargement',
    value: '${(value * 100).round()} %',
    child: ClipRRect(
      borderRadius: BorderRadius.circular(99),
      child: SizedBox(
        width: width,
        height: 7,
        child: Stack(
          children: [
            const Positioned.fill(child: ColoredBox(color: Color(0x59FFFFFF))),
            AnimatedFractionallySizedBox(
              duration: const Duration(milliseconds: 350),
              curve: Curves.easeOut,
              widthFactor: value.clamp(0.0, 1.0),
              heightFactor: 1,
              alignment: Alignment.centerLeft,
              child: const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [authOrange, authOrange, Colors.white, Colors.white],
                    stops: [0, .5, .5, 1],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
