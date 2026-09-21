import 'package:flutter/material.dart';

import '../core/network/api_client.dart';
import '../core/storage/token_storage.dart';
import '../features/auth/login_screen.dart';
import '../features/driver/data/driver_repository.dart';
import '../features/home/home_screen.dart';
import 'theme.dart';

class OvanieLivreurApp extends StatelessWidget {
  const OvanieLivreurApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'OVANIE Livreur',
      theme: OvanieTheme.light,
      home: const _SessionGate(),
    );
  }
}

/// Restaure silencieusement la session du livreur au redémarrage de
/// l'application.
///
/// Sur certains téléphones Android (notamment avec peu de mémoire), le système
/// peut tuer le processus lorsqu'on passe quelques instants dans une autre
/// application. Le jeton Sanctum est déjà conservé dans SharedPreferences : on
/// doit donc le réutiliser au retour au lieu de redemander un OTP à chaque fois.
class _SessionGate extends StatefulWidget {
  const _SessionGate();

  @override
  State<_SessionGate> createState() => _SessionGateState();
}

class _SessionGateState extends State<_SessionGate> {
  late final Future<_SessionTarget> _session = _restoreSession();

  Future<_SessionTarget> _restoreSession() async {
    final token = (await TokenStorage.instance.readToken())?.trim() ?? '';
    if (token.isEmpty) {
      ApiClient.setBearerToken(null);
      return _SessionTarget.login;
    }

    ApiClient.setBearerToken(token);

    try {
      // Une requête légère confirme que le jeton est encore reconnu par
      // Laravel. Le profil sera rechargé ensuite par HomeScreen.
      await DriverRepository.instance.me();
      return _SessionTarget.home;
    } on OvanieApiException catch (error) {
      // On détruit la session locale uniquement lorsque le serveur confirme
      // réellement que le jeton n'est plus valable. Une coupure réseau ne doit
      // jamais provoquer une nouvelle demande OTP.
      if (error.statusCode == 401) {
        await TokenStorage.instance.clear();
        ApiClient.setBearerToken(null);
        return _SessionTarget.login;
      }
      return _SessionTarget.home;
    } catch (_) {
      // Réseau indisponible / timeout : conserver la session. HomeScreen
      // affichera son état de reconnexion et pourra réessayer sans OTP.
      return _SessionTarget.home;
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<_SessionTarget>(
      future: _session,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const _SessionLoadingScreen();
        }

        return snapshot.data == _SessionTarget.home
            ? const HomeScreen()
            : const LoginScreen();
      },
    );
  }
}

enum _SessionTarget { login, home }

class _SessionLoadingScreen extends StatelessWidget {
  const _SessionLoadingScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: OvanieColors.background,
      body: SafeArea(
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'OVANIE Logistics',
                style: TextStyle(
                  color: OvanieColors.greenDark,
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                ),
              ),
              SizedBox(height: 18),
              SizedBox(
                width: 28,
                height: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 3,
                  color: OvanieColors.green,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
