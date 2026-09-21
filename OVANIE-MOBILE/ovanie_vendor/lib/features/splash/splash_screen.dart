import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/ui/vendor_design.dart';
import '../auth/vendor_journey_screen.dart';
import '../auth/vendor_session.dart';
import '../onboarding/shop_onboarding_screen.dart';
import '../shell/vendor_shell.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _routing = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _boot());
  }

  Future<void> _boot() async {
    if (_routing) return;
    _routing = true;

    Widget target = const VendorJourneyScreen();
    try {
      await VendorSession.instance.restore();
      if (!mounted) return;

      final session = VendorSession.instance;
      if (session.authenticated) {
        target = session.hasShop
            ? const VendorShell()
            : const ShopOnboardingScreen();
      }
    } catch (_) {
      // Un problème de stockage local ou de réseau ne doit jamais laisser
      // l'application sur un écran vide. Le vendeur peut toujours repartir
      // du choix de parcours puis se reconnecter.
      target = const VendorJourneyScreen();
    }

    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => target),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
      ),
      child: Scaffold(
        backgroundColor: vendorNavyDeep,
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Image.asset('assets/images/ovanie_logo.png', width: 235),
              const SizedBox(height: 28),
              const SizedBox.square(
                dimension: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.4,
                  color: Colors.white,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
