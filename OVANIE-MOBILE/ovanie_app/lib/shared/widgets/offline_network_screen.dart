import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_runtime_state.dart';
import '../../features/auth/data/auth_repository.dart';

class OfflineNetworkScreen extends StatefulWidget {
  const OfflineNetworkScreen({super.key});

  @override
  State<OfflineNetworkScreen> createState() => _OfflineNetworkScreenState();
}

class _OfflineNetworkScreenState extends State<OfflineNetworkScreen> {
  bool _retrying = false;
  String? _retryError;

  Future<void> _retry() async {
    if (_retrying) return;
    setState(() {
      _retrying = true;
      _retryError = null;
    });

    try {
      await const AuthRepository().checkServer();
      ApiRuntimeState.instance.reportOnline();
    } catch (error) {
      if (!mounted) return;
      setState(() => _retryError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _retrying = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final runtime = ApiRuntimeState.instance;
    return Material(
      color: OvanieColors.background,
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(28, 36, 28, 36),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 86,
                    height: 86,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF4EC),
                      borderRadius: BorderRadius.circular(28),
                    ),
                    child: const Icon(
                      Icons.wifi_off_rounded,
                      size: 42,
                      color: OvanieColors.orange,
                    ),
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'Connexion indisponible',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.text,
                      fontSize: 23,
                      height: 1.15,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    _retryError ?? runtime.networkMessage,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 13.5,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Votre panier local et votre contexte de navigation sont conservés sur ce téléphone.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: OvanieColors.muted,
                      fontSize: 12,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: FilledButton.icon(
                      onPressed: _retrying ? null : _retry,
                      style: FilledButton.styleFrom(
                        backgroundColor: OvanieColors.orange,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      icon: _retrying
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.refresh_rounded),
                      label: Text(
                        _retrying ? 'Vérification…' : 'Réessayer',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

