import 'dart:async';

import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_runtime_state.dart';
import '../../auth/data/auth_repository.dart';
import '../../auth/domain/session_store.dart';
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
    if (_waitingForNetwork &&
        !runtime.isOffline &&
        revision != _lastConnectivityRevision) {
      _lastConnectivityRevision = revision;
      unawaited(_bootstrap());
    }
  }

  Future<void> _bootstrap() async {
    if (_booting || _completed) return;
    _booting = true;
    _waitingForNetwork = false;

    try {
      _setStep('Restauration de votre session…');
      await SessionStore.instance.restore();

      _setStep('Restauration de votre panier…');
      await CartStore.instance.restore();

      _setStep('Connexion à OVANIE…');
      await const AuthRepository().checkServer();

      if (SessionStore.instance.isAuthenticated) {
        _setStep('Vérification sécurisée de votre session…');
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
        _setStep('Synchronisation de votre panier…');
        await const CartApiRepository().refreshLocalCartFromServer();

        // Les favoris ne bloquent pas l'ouverture de l'accueil mais sont
        // rafraîchis dès que la session vient d'être confirmée.
        unawaited(FavoritesStore.instance.refreshFromServer());
      }

      if (!mounted) return;
      _completed = true;
      ApiRuntimeState.instance.markBootCompleted();
      Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(builder: (_) => const MainShell()),
      );
    } catch (error) {
      if (_isNetworkFailure(error)) {
        _waitingForNetwork = true;
        ApiRuntimeState.instance.reportOffline(ApiClient.friendlyError(error));
      } else if (error is OvanieApiException && error.statusCode == 401) {
        await SessionStore.instance.expireFromServer();
        if (!mounted) return;
        _completed = true;
        ApiRuntimeState.instance.markBootCompleted();
        Navigator.of(context).pushReplacement(
          MaterialPageRoute<void>(builder: (_) => const MainShell()),
        );
      } else {
        // Une réponse serveur non réseau est affichée sous forme d'état propre
        // dans le Splash, avec possibilité de relancer l'initialisation.
        if (mounted) {
          setState(() => _step = ApiClient.friendlyError(error));
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

  void _setStep(String value) {
    if (!mounted) return;
    setState(() => _step = value);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: OvanieColors.navyDark,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 42),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Image.asset(
                  'assets/images/ovanie_logo.png',
                  width: 235,
                  fit: BoxFit.contain,
                ),
                const SizedBox(height: 30),
                const SizedBox(
                  width: 28,
                  height: 28,
                  child: CircularProgressIndicator(
                    strokeWidth: 2.6,
                    color: OvanieColors.orange,
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  _step,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 12.5,
                    height: 1.4,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (!_booting && !_waitingForNetwork && !_completed) ...[
                  const SizedBox(height: 16),
                  TextButton.icon(
                    onPressed: _bootstrap,
                    style: TextButton.styleFrom(foregroundColor: Colors.white),
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Réessayer'),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
