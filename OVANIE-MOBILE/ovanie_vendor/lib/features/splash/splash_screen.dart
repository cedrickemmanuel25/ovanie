import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/ui/ovanie_ui.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../auth/vendor_journey_screen.dart';
import '../auth/vendor_session.dart';
import '../onboarding/shop_onboarding_screen.dart';
import '../shell/vendor_shell.dart';

/// Écran de lancement : la photo du magasin avec le logo « OVANIE Vendeur » en
/// plein écran, et un anneau de chargement qui tourne pendant que la session
/// est restaurée.
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  static Completer<void> _finished = Completer<void>();

  /// Se termine quand l'écran de lancement a cédé la place à l'écran suivant.
  /// Les liens profonds l'attendent pour ne pas être écrasés par cette navigation.
  static Future<void> get finished => _finished.future;

  /// Vérifications de démarrage (compatibilité, données de référence) lancées
  /// par `main` : l'écran de lancement les attend avant de passer à la suite.
  static Future<void> startup = Future<void>.value();

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  /// Durée minimale d'affichage, pour que le logo ait le temps d'être vu.
  static const _minimum = Duration(milliseconds: 1600);

  // Dimensions de la photo et position de l'anneau dans la maquette.
  static const _photoWidth = 941.0;
  static const _photoHeight = 1672.0;
  static const _ringCenter = Offset(471, 1497);
  static const _ringRadius = 37.0;

  late final AnimationController _spin = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  );

  bool _started = false;

  @override
  void initState() {
    super.initState();
    if (SplashScreen._finished.isCompleted) {
      SplashScreen._finished = Completer<void>();
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_started) return;
    _started = true;
    precacheImage(const AssetImage('assets/images/mockups/journey.jpg'), context);
    // Animations réduites sur le téléphone : l'anneau reste fixe.
    if (!ovReduceMotion(context)) _spin.repeat();
    WidgetsBinding.instance.addPostFrameCallback((_) => _boot());
  }

  Future<Widget> _resolveTarget() async {
    try {
      await SplashScreen.startup;
      await VendorSession.instance.restore();
      final session = VendorSession.instance;
      if (session.authenticated) {
        return session.hasShop ? const VendorShell() : const ShopOnboardingScreen();
      }
    } catch (_) {
      // Un problème de stockage local ou de réseau ne doit jamais laisser
      // l'application sur l'écran de lancement : le vendeur repart de
      // l'accueil et peut se reconnecter.
    }
    return const VendorJourneyScreen();
  }

  Future<void> _boot() async {
    final target = _resolveTarget();
    await Future<void>.delayed(_minimum);
    final page = await target;
    if (!mounted) return;

    await Navigator.of(context).pushAndRemoveUntil(
      ovFadeRoute<void>(page),
      (route) => false,
    );
  }

  @override
  void dispose() {
    if (!SplashScreen._finished.isCompleted) SplashScreen._finished.complete();
    _spin.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
        systemNavigationBarColor: const Color(0xFFDBC6B8),
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: const Color(0xFF005BEB),
        body: Semantics(
          label: 'OVANIE Vendeur, chargement',
          child: ExcludeSemantics(
            child: LayoutBuilder(
              builder: (context, box) {
                // La photo couvre l'écran : l'anneau suit le même cadrage.
                final scale = math.max(box.maxWidth / _photoWidth, box.maxHeight / _photoHeight);
                final dx = (box.maxWidth - _photoWidth * scale) / 2;
                final dy = (box.maxHeight - _photoHeight * scale) / 2;
                final radius = _ringRadius * scale;
                final bottomLimit = box.maxHeight - MediaQuery.paddingOf(context).bottom - radius - 20;
                final center = Offset(
                  dx + _ringCenter.dx * scale,
                  math.min(dy + _ringCenter.dy * scale, bottomLimit),
                );

                return Stack(
                  fit: StackFit.expand,
                  children: <Widget>[
                    // La photo apparaît en fondu sur le bleu de l'écran de démarrage
                    // d'Android : les deux écrans s'enchaînent comme un seul.
                    Image.asset(
                      MkPhoto.asset('splash'),
                      fit: BoxFit.cover,
                      frameBuilder: (context, child, frame, wasSynchronouslyLoaded) => AnimatedOpacity(
                        opacity: frame == null && !wasSynchronouslyLoaded ? 0 : 1,
                        duration: const Duration(milliseconds: 380),
                        curve: Curves.easeOut,
                        child: child,
                      ),
                    ),
                    Positioned(
                      left: center.dx - radius,
                      top: center.dy - radius,
                      width: radius * 2,
                      height: radius * 2,
                      child: RotationTransition(
                        turns: _spin,
                        child: CustomPaint(painter: _RingPainter()),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }
}

/// Anneau blanc dont un quart est orange.
class _RingPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final stroke = size.width * .085;
    final rect = (Offset.zero & size).deflate(stroke / 2);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, math.pi * 2, false, paint..color = Colors.white);
    canvas.drawArc(rect, -math.pi / 2.4, math.pi / 2, false, paint..color = Mk.orange);
  }

  @override
  bool shouldRepaint(_RingPainter oldDelegate) => false;
}
