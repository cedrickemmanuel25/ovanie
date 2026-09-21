import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/ui/vendor_design.dart';
import '../dashboard/vendor_dashboard_screen.dart';
import '../finance/finance_screen.dart';
import '../orders/orders_screen.dart';
import '../products/products_screen.dart';
import 'vendor_menu_screen.dart';
import 'vendor_tabs.dart';

class VendorShell extends StatefulWidget {
  const VendorShell({super.key});

  @override
  State<VendorShell> createState() => _VendorShellState();
}

class _VendorShellState extends State<VendorShell> {
  late VendorTab _tab;
  late final List<Widget> _pages;

  @override
  void initState() {
    super.initState();
    _tab = vendorTabNotifier.value;
    vendorTabNotifier.addListener(_syncExternalTab);
    _pages = [
      VendorDashboardScreen(onSelectTab: _selectTab),
      const ProductsScreen(),
      const OrdersScreen(),
      const FinanceScreen(),
      VendorMenuScreen(onSelectTab: _selectTab),
    ];
  }

  @override
  void dispose() {
    vendorTabNotifier.removeListener(_syncExternalTab);
    super.dispose();
  }

  void _syncExternalTab() {
    if (!mounted || vendorTabNotifier.value == _tab) return;
    setState(() => _tab = vendorTabNotifier.value);
  }

  void _selectTab(VendorTab tab) {
    if (!mounted) return;
    vendorTabNotifier.value = tab;
    setState(() => _tab = tab);
  }

  int get _index => VendorTab.values.indexOf(_tab);

  @override
  Widget build(BuildContext context) {
    final home = _tab == VendorTab.home;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: home
          ? SystemUiOverlayStyle.light.copyWith(
              statusBarColor: Colors.transparent,
            )
          : SystemUiOverlayStyle.light.copyWith(
              statusBarColor: Colors.transparent,
            ),
      child: Scaffold(
        backgroundColor: vendorPage,
        // Ne construire que l'onglet visible. IndexedStack construisait les
        // cinq écrans dès l'ouverture de l'accueil ; une exception de layout
        // dans un onglet encore masqué pouvait donc rendre tout le shell blanc.
        body: _pages[_index],
        bottomNavigationBar: VendorBottomBar(
          selected: _tab,
          onSelected: _selectTab,
        ),
      ),
    );
  }
}
