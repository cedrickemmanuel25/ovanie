import 'package:flutter/material.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_runtime_state.dart';
import '../../features/auth/data/auth_repository.dart';
import '../../features/auth/presentation/auth_branding.dart';

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
    return OvanieStatusPage(
      art: 'offline',
      artAspect: 941 / 815,
      title: 'Connexion indisponible',
      message: 'Impossible de joindre OVANIE. Vérifiez votre connexion internet puis réessayez.',
      extras: [
        const OvanieInfoStrip(
          icon: Icons.shopping_cart_outlined,
          text: 'Votre panier local et votre contexte de navigation sont conservés sur ce téléphone.',
        ),
        if (AppConfig.isLocalDevelopment)
          SelectableText(
            'Serveur configuré : ${AppConfig.apiBaseUrl}${_retryError == null ? '' : '\n$_retryError'}',
            textAlign: TextAlign.center,
            style: const TextStyle(color: authMuted, fontSize: 12, height: 1.4),
          ),
      ],
      button: OvaniePrimaryButton(
        label: 'Réessayer',
        loading: _retrying,
        loadingLabel: 'Vérification…',
        height: 54,
        onPressed: _retry,
      ),
    );
  }
}
