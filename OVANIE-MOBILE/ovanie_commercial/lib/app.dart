import 'package:flutter/material.dart';

import 'core/api/api_client.dart';
import 'core/storage/session_storage.dart';
import 'core/theme/ovanie_colors.dart';
import 'features/auth/data/commercial_auth_service.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/dashboard/data/dashboard_service.dart';
import 'features/dashboard/presentation/home_screen.dart';
import 'features/clients/data/clients_service.dart';
import 'features/shops/data/shops_service.dart';
import 'features/products/data/products_service.dart';
import 'features/menu/data/menu_service.dart';
import 'features/prospecting/data/prospecting_service.dart';

class OvanieCommercialApp extends StatefulWidget {
  const OvanieCommercialApp({super.key});

  @override
  State<OvanieCommercialApp> createState() => _OvanieCommercialAppState();
}

class _OvanieCommercialAppState extends State<OvanieCommercialApp> {
  final ApiClient _api = ApiClient();
  final SessionStorage _storage = const SessionStorage();

  late final CommercialAuthService _auth = CommercialAuthService(_api);
  late final DashboardService _dashboard = DashboardService(_api);
  late final ClientsService _clients = ClientsService(_api);
  late final ShopsService _shops = ShopsService(_api);
  late final ProductsService _products = ProductsService(_api);
  late final MenuService _menu = MenuService(_api);
  late final ProspectingService _prospecting = ProspectingService(_api);

  bool _booting = true;
  bool _authenticated = false;
  Map<String, dynamic> _user = const {};

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final token = await _storage.readToken();
    if (token == null || token.isEmpty) {
      if (mounted) setState(() => _booting = false);
      return;
    }

    try {
      final user = await _auth.me(token);
      if (!mounted) return;
      setState(() {
        _user = user;
        _authenticated = true;
        _booting = false;
      });
    } catch (_) {
      await _storage.clearToken();
      _api.setToken(null);
      if (mounted) setState(() => _booting = false);
    }
  }

  Future<void> _onLoggedIn(
    CommercialSession session, {
    required bool rememberMe,
  }) async {
    if (rememberMe) {
      await _storage.saveToken(session.token);
    } else {
      await _storage.clearToken();
    }

    if (!mounted) return;
    setState(() {
      _user = session.user;
      _authenticated = true;
    });
  }

  Future<void> _logout() async {
    await _storage.clearToken();
    try {
      await _auth.logout();
    } catch (_) {
      _api.setToken(null);
    }
    if (!mounted) return;
    setState(() {
      _authenticated = false;
      _user = const {};
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'OVANIE Commercial',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        scaffoldBackgroundColor: OvanieColors.navy950,
        colorScheme: ColorScheme.fromSeed(
          seedColor: OvanieColors.blue,
          brightness: Brightness.dark,
        ),
        textSelectionTheme: const TextSelectionThemeData(
          cursorColor: OvanieColors.blue,
          selectionColor: Color(0x553B9FFF),
          selectionHandleColor: OvanieColors.blue,
        ),
      ),
      builder: (context, child) {
        final mediaQuery = MediaQuery.of(context);
        final clampedScaler = mediaQuery.textScaler.clamp(minScaleFactor: 0.9, maxScaleFactor: 1.15);
        return MediaQuery(
          data: mediaQuery.copyWith(textScaler: clampedScaler),
          child: child!,
        );
      },
      home: AnimatedSwitcher(
        duration: const Duration(milliseconds: 240),
        child: _booting
            ? const _BootScreen(key: ValueKey('boot'))
            : _authenticated
                ? CommercialHomeScreen(
                    key: const ValueKey('home'),
                    dashboardService: _dashboard,
                    clientsService: _clients,
                    shopsService: _shops,
                    productsService: _products,
                    menuService: _menu,
                    prospectingService: _prospecting,
                    initialUser: _user,
                    onLogout: _logout,
                  )
                : CommercialLoginScreen(
                    key: const ValueKey('login'),
                    authService: _auth,
                    onLoggedIn: _onLoggedIn,
                  ),
      ),
    );
  }
}

class _BootScreen extends StatelessWidget {
  const _BootScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: SizedBox(
          width: 32,
          height: 32,
          child: CircularProgressIndicator(strokeWidth: 2.4),
        ),
      ),
    );
  }
}
