import 'dart:async';

import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../shared/widgets/ovanie_widgets.dart';
import '../driver/data/driver_repository.dart';
import '../driver/models/driver_profile.dart';
import '../shell/driver_shell.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  DriverProfile? _driver;
  Timer? _verificationTimer;

  @override
  void initState() {
    super.initState();
    _load();
    _verificationTimer = Timer.periodic(const Duration(seconds: 20), (_) {
      if (!mounted || _loading || _isActive) return;
      _load();
    });
  }

  @override
  void dispose() {
    _verificationTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final driver = await DriverRepository.instance.me();
      if (mounted) {
        setState(() => _driver = driver);
        if ((driver.onboardingStatus ?? '') == 'active') {
          _verificationTimer?.cancel();
        }
      }
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  bool get _isActive => (_driver?.onboardingStatus ?? '') == 'active';

  @override
  Widget build(BuildContext context) {
    // Livreur actif : la navigation basse (missions, suivi, historique,
    // profil) prend le relais de cet écran d'attente. La décision reste ici
    // pour ne pas dupliquer la logique `_isActive` / chargement ailleurs.
    if (!_loading && _driver != null && _isActive) {
      return DriverShell(driver: _driver!, onRefresh: _load);
    }

    return Scaffold(
      backgroundColor: OvanieColors.background,
      appBar: AppBar(
        title: const Text('OVANIE Livreur'),
        actions: [
          IconButton(
            onPressed: _load,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
          child: OvanieCenteredScroll(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_loading)
                  const ClipRRect(
                    borderRadius: BorderRadius.all(Radius.circular(999)),
                    child: LinearProgressIndicator(color: OvanieColors.green, minHeight: 4),
                  ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  OvanieErrorBox(_error),
                ],
                if (!_loading && _driver != null && !_isActive) ...[
                  const SizedBox(height: 12),
                  const OvanieInfoBox(
                    icon: Icons.hourglass_top_rounded,
                    child: Text(
                      'Votre dossier est en cours de vérification par OVANIE Logistics',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: OvanieColors.text, height: 1.45, fontSize: 15, fontWeight: FontWeight.w700),
                    ),
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
