import 'package:flutter/material.dart';

class CommercialShopSearch extends StatelessWidget {
  const CommercialShopSearch({
    super.key,
    required this.controller,
    required this.scale,
    required this.onChanged,
    required this.onFilterTap,
    this.compactFilter = false,
  });

  final TextEditingController controller;
  final double scale;
  final ValueChanged<String> onChanged;
  final VoidCallback onFilterTap;
  final bool compactFilter;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      children: [
        Expanded(
          child: Container(
            height: s(48),
            decoration: BoxDecoration(
              color: const Color(0xFF0B2852).withOpacity(.91),
              borderRadius: BorderRadius.circular(s(13)),
              border: Border.all(color: const Color(0xFF2A5487), width: .85),
            ),
            child: Row(
              children: [
                SizedBox(width: s(13)),
                Icon(Icons.search_rounded, color: const Color(0xFFB5C3DA), size: s(25)),
                SizedBox(width: s(9)),
                Expanded(
                  child: TextField(
                    controller: controller,
                    onChanged: onChanged,
                    textInputAction: TextInputAction.search,
                    style: TextStyle(color: Colors.white, fontSize: s(12.2)),
                    decoration: InputDecoration(
                      border: InputBorder.none,
                      isDense: true,
                      hintText: 'Rechercher une boutique (nom, propriétaire, ville...)',
                      hintStyle: TextStyle(
                        color: const Color(0xFFAEB9D0),
                        fontSize: s(11.5),
                      ),
                    ),
                  ),
                ),
                if (controller.text.isNotEmpty)
                  IconButton(
                    onPressed: () {
                      controller.clear();
                      onChanged('');
                    },
                    icon: Icon(Icons.close_rounded, color: const Color(0xFF94A6C4), size: s(18)),
                  ),
              ],
            ),
          ),
        ),
        SizedBox(width: s(10)),
        InkWell(
          onTap: onFilterTap,
          borderRadius: BorderRadius.circular(s(13)),
          child: Container(
            height: s(48),
            padding: EdgeInsets.symmetric(horizontal: s(compactFilter ? 12 : 14)),
            decoration: BoxDecoration(
              color: const Color(0xFF0B2852).withOpacity(.95),
              borderRadius: BorderRadius.circular(s(13)),
              border: Border.all(color: const Color(0xFF2A5487), width: .85),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.tune_rounded, color: const Color(0xFFBEC9DC), size: s(22)),
                if (!compactFilter) ...[
                  SizedBox(width: s(7)),
                  Text(
                    'Filtres',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: s(11.5),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class ShopStatusBadge extends StatelessWidget {
  const ShopStatusBadge({super.key, required this.status, required this.label, required this.scale});

  final String status;
  final String label;
  final double scale;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    final normalized = status.toLowerCase();
    final (foreground, background, dot) = switch (normalized) {
      'online' => (const Color(0xFF008B49), const Color(0xFFDDF7E8), const Color(0xFF27C77B)),
      'suspended' => (const Color(0xFFEA3151), const Color(0xFFFDE3EA), const Color(0xFFFF3C58)),
      _ => (const Color(0xFFB66B00), const Color(0xFFFFF3D6), const Color(0xFFFFB117)),
    };

    return Container(
      padding: EdgeInsets.symmetric(horizontal: s(10), vertical: s(6)),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(s(18)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: s(7),
            height: s(7),
            decoration: BoxDecoration(color: dot, shape: BoxShape.circle),
          ),
          SizedBox(width: s(7)),
          Text(
            label,
            style: TextStyle(color: foreground, fontSize: s(10.4), fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}
