import 'package:flutter/material.dart';

import '../../core/ui/vendor_design.dart';

enum VendorTab { home, products, orders, finance, menu }

class VendorTabDefinition {
  const VendorTabDefinition({
    required this.tab,
    required this.label,
    required this.icon,
    required this.selectedIcon,
  });

  final VendorTab tab;
  final String label;
  final IconData icon;
  final IconData selectedIcon;
}

const vendorTabs = <VendorTabDefinition>[
  VendorTabDefinition(tab: VendorTab.home, label: 'Accueil', icon: Icons.home_outlined, selectedIcon: Icons.home_rounded),
  VendorTabDefinition(tab: VendorTab.products, label: 'Produits', icon: Icons.inventory_2_outlined, selectedIcon: Icons.inventory_2_rounded),
  VendorTabDefinition(tab: VendorTab.orders, label: 'Commandes', icon: Icons.assignment_outlined, selectedIcon: Icons.assignment_rounded),
  VendorTabDefinition(tab: VendorTab.finance, label: 'Finances', icon: Icons.account_balance_wallet_outlined, selectedIcon: Icons.account_balance_wallet_rounded),
  VendorTabDefinition(tab: VendorTab.menu, label: 'Menu', icon: Icons.menu_rounded, selectedIcon: Icons.menu_rounded),
];

/// Source unique permettant aux écrans secondaires (fiche produit, images,
/// archivage...) de revenir proprement vers l'un des 5 onglets racine.
final ValueNotifier<VendorTab> vendorTabNotifier = ValueNotifier<VendorTab>(VendorTab.home);

void goToVendorTab(BuildContext context, VendorTab tab) {
  vendorTabNotifier.value = tab;
  Navigator.of(context).popUntil((route) => route.isFirst);
}

class VendorBottomBar extends StatelessWidget {
  const VendorBottomBar({
    super.key,
    required this.selected,
    this.onSelected,
  });

  final VendorTab selected;
  final ValueChanged<VendorTab>? onSelected;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        height: 70,
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(top: BorderSide(color: Color(0xFFE8ECF2))),
          boxShadow: [BoxShadow(color: Color(0x10000F35), blurRadius: 14, offset: Offset(0, -3))],
        ),
        child: Row(
          children: vendorTabs.map((item) {
            final active = selected == item.tab;
            return Expanded(
              child: InkWell(
                onTap: () {
                  if (onSelected != null) {
                    onSelected!(item.tab);
                  } else {
                    goToVendorTab(context, item.tab);
                  }
                },
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(active ? item.selectedIcon : item.icon, color: active ? vendorOrange : vendorText, size: 25),
                    const SizedBox(height: 4),
                    Text(
                      item.label,
                      maxLines: 1,
                      overflow: TextOverflow.fade,
                      style: TextStyle(
                        color: active ? vendorOrange : vendorText,
                        fontSize: 11.5,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      width: active ? 22 : 0,
                      height: 3,
                      decoration: BoxDecoration(color: active ? vendorOrange : Colors.transparent, borderRadius: BorderRadius.circular(99)),
                    ),
                  ],
                ),
              ),
            );
          }).toList(),
        ),
      ),
    );
  }
}
