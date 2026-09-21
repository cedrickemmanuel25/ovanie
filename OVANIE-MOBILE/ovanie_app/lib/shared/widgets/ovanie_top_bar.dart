import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../features/cart/domain/cart_store.dart';
import 'ovanie_search_bar.dart';

class OvanieMenuAction {
  final String value;
  final String label;
  final IconData icon;

  const OvanieMenuAction({
    required this.value,
    required this.label,
    required this.icon,
  });
}

class OvanieTopBar extends StatelessWidget {
  final bool showBack;
  final VoidCallback? onBack;
  final VoidCallback onOpenCart;
  final List<OvanieMenuAction> menuActions;
  final ValueChanged<String>? onMenuSelected;
  final String searchHint;

  const OvanieTopBar({
    super.key,
    this.showBack = false,
    this.onBack,
    required this.onOpenCart,
    this.menuActions = const [],
    this.onMenuSelected,
    this.searchHint = 'Rechercher un produit…',
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
      child: Row(
        children: [
          if (showBack) ...[
            IconButton(
              tooltip: 'Retour',
              onPressed: onBack ?? () => Navigator.of(context).maybePop(),
              icon: const Icon(Icons.arrow_back_rounded),
              color: OvanieColors.navy,
              visualDensity: VisualDensity.compact,
            ),
            const SizedBox(width: 4),
          ],
          Expanded(
            child: OvanieSearchBar(
              hintText: searchHint,
              height: 46,
            ),
          ),
          const SizedBox(width: 8),
          AnimatedBuilder(
            animation: CartStore.instance,
            builder: (context, _) {
              final count = CartStore.instance.itemsCount;
              return Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(13),
                child: InkWell(
                  onTap: onOpenCart,
                  borderRadius: BorderRadius.circular(13),
                  child: Container(
                    width: 48,
                    height: 46,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(13),
                      border: Border.all(color: OvanieColors.border),
                    ),
                    child: Badge.count(
                      count: count,
                      isLabelVisible: count > 0,
                      backgroundColor: OvanieColors.orange,
                      child: const Icon(
                        Icons.shopping_cart_outlined,
                        color: OvanieColors.navy,
                        size: 23,
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
          if (menuActions.isNotEmpty) ...[
            const SizedBox(width: 6),
            Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(13),
              child: Container(
                width: 44,
                height: 46,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(13),
                  border: Border.all(color: OvanieColors.border),
                ),
                child: PopupMenuButton<String>(
                  tooltip: 'Menu',
                  padding: EdgeInsets.zero,
                  icon: const Icon(
                    Icons.more_vert_rounded,
                    color: OvanieColors.navy,
                    size: 24,
                  ),
                  onSelected: onMenuSelected,
                  itemBuilder: (context) => menuActions
                      .map(
                        (item) => PopupMenuItem<String>(
                          value: item.value,
                          child: Row(
                            children: [
                              Icon(item.icon, size: 20, color: OvanieColors.navy),
                              const SizedBox(width: 10),
                              Text(
                                item.label,
                                style: const TextStyle(
                                  color: OvanieColors.text,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                        ),
                      )
                      .toList(),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
