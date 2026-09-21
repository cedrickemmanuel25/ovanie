import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../../dashboard/models/dashboard_data.dart';

class CommercialClientsHeader extends StatelessWidget {
  const CommercialClientsHeader({
    super.key,
    required this.profile,
    required this.unreadNotifications,
    required this.scale,
    required this.onNotificationsTap,
    required this.onAvatarTap,
  });

  final CommercialProfile profile;
  final int unreadNotifications;
  final double scale;
  final VoidCallback onNotificationsTap;
  final VoidCallback onAvatarTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return SizedBox(
      height: s(74),
      child: Row(
        children: [
          Image.asset(
            'assets/images/ovanie_logo.png',
            width: s(148),
            fit: BoxFit.contain,
          ),
          Container(
            height: s(38),
            width: 1,
            margin: EdgeInsets.symmetric(horizontal: s(12)),
            color: const Color(0xFFB7C3D8).withOpacity(.55),
          ),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Bonjour ${profile.firstName}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: s(17.2),
                    height: 1.05,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -.25,
                  ),
                ),
                SizedBox(height: s(5)),
                Text(
                  'Prête pour vos actions terrain aujourd’hui',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: const Color(0xFFC6CFDF),
                    fontSize: s(10.5),
                  ),
                ),
              ],
            ),
          ),
          SizedBox(width: s(7)),
          InkWell(
            onTap: onNotificationsTap,
            customBorder: const CircleBorder(),
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Container(
                  width: s(48),
                  height: s(48),
                  decoration: BoxDecoration(
                    color: const Color(0xFF05214B),
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFF24538B)),
                  ),
                  child: Icon(
                    Icons.notifications_none_rounded,
                    color: Colors.white,
                    size: s(27),
                  ),
                ),
                if (unreadNotifications > 0)
                  Positioned(
                    right: s(-1),
                    top: s(-4),
                    child: Container(
                      constraints: BoxConstraints(minWidth: s(18), minHeight: s(18)),
                      padding: EdgeInsets.symmetric(horizontal: s(4)),
                      decoration: const BoxDecoration(
                        color: OvanieColors.orange,
                        shape: BoxShape.circle,
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        unreadNotifications > 99 ? '99+' : '$unreadNotifications',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: s(8.4),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          SizedBox(width: s(8)),
          InkWell(
            onTap: onAvatarTap,
            customBorder: const CircleBorder(),
            child: Container(
              width: s(49),
              height: s(49),
              padding: EdgeInsets.all(s(2)),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: OvanieColors.blue, width: 1.1),
                color: const Color(0xFF153D70),
              ),
              child: ClipOval(
                child: profile.avatarUrl != null
                    ? Image.network(
                        profile.avatarUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _AvatarFallback(
                          name: profile.name,
                          scale: scale,
                        ),
                      )
                    : _AvatarFallback(name: profile.name, scale: scale),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AvatarFallback extends StatelessWidget {
  const _AvatarFallback({required this.name, required this.scale});

  final String name;
  final double scale;

  @override
  Widget build(BuildContext context) {
    final initials = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0].toUpperCase())
        .join();
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFE9D1BA), Color(0xFF7F6048)],
        ),
      ),
      child: Center(
        child: Text(
          initials.isEmpty ? 'OC' : initials,
          style: TextStyle(
            color: Colors.white,
            fontSize: 14 * scale,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class CommercialClientSearch extends StatelessWidget {
  const CommercialClientSearch({
    super.key,
    required this.controller,
    required this.scale,
    required this.onChanged,
    required this.onFilterTap,
  });

  final TextEditingController controller;
  final double scale;
  final ValueChanged<String> onChanged;
  final VoidCallback onFilterTap;

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
              border: Border.all(color: const Color(0xFF254B78), width: .8),
            ),
            child: Row(
              children: [
                SizedBox(width: s(13)),
                Icon(Icons.search_rounded, color: const Color(0xFFAFBDD5), size: s(25)),
                SizedBox(width: s(9)),
                Expanded(
                  child: TextField(
                    controller: controller,
                    onChanged: onChanged,
                    textInputAction: TextInputAction.search,
                    style: TextStyle(color: Colors.white, fontSize: s(12.5)),
                    decoration: InputDecoration(
                      border: InputBorder.none,
                      isDense: true,
                      hintText: 'Rechercher un client, téléphone ou entreprise...',
                      hintStyle: TextStyle(
                        color: const Color(0xFFAEB9D0),
                        fontSize: s(12.2),
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
            width: s(50),
            height: s(48),
            decoration: BoxDecoration(
              color: const Color(0xFF0B2852).withOpacity(.95),
              borderRadius: BorderRadius.circular(s(13)),
              border: Border.all(color: const Color(0xFF254B78), width: .8),
            ),
            child: Icon(Icons.tune_rounded, color: const Color(0xFFBEC9DC), size: s(23)),
          ),
        ),
      ],
    );
  }
}

class CommercialBottomNavigation extends StatelessWidget {
  const CommercialBottomNavigation({
    super.key,
    required this.scale,
    required this.currentIndex,
    required this.onTap,
  });

  final double scale;
  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    const items = [
      (Icons.home_outlined, 'Accueil'),
      (Icons.groups_2_outlined, 'Clients'),
      (Icons.storefront_outlined, 'Boutiques'),
      (Icons.inventory_2_outlined, 'Produits'),
      (Icons.menu_rounded, 'Menu'),
    ];

    return SafeArea(
      top: false,
      minimum: EdgeInsets.fromLTRB(s(15), 0, s(15), s(8)),
      child: Container(
        height: s(66),
        padding: EdgeInsets.symmetric(horizontal: s(5), vertical: s(4)),
        decoration: BoxDecoration(
          color: const Color(0xFF031B42).withOpacity(.98),
          borderRadius: BorderRadius.circular(s(32)),
          border: Border.all(color: const Color(0xFF244B7A), width: .8),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(.25),
              blurRadius: 16,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        child: Row(
          children: List.generate(items.length, (index) {
            final active = index == currentIndex;
            final item = items[index];
            return Expanded(
              child: InkWell(
                onTap: () => onTap(index),
                borderRadius: BorderRadius.circular(s(27)),
                child: Container(
                  decoration: active
                      ? BoxDecoration(
                          color: const Color(0xFF103D78),
                          borderRadius: BorderRadius.circular(s(26)),
                          border: Border.all(color: const Color(0xFF176BD2), width: .8),
                        )
                      : null,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        item.$1,
                        color: active ? const Color(0xFF2688FF) : Colors.white,
                        size: s(23),
                      ),
                      SizedBox(height: s(2)),
                      Text(
                        item.$2,
                        style: TextStyle(
                          color: active ? const Color(0xFF2688FF) : Colors.white,
                          fontSize: s(9.3),
                          fontWeight: active ? FontWeight.w600 : FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }),
        ),
      ),
    );
  }
}
