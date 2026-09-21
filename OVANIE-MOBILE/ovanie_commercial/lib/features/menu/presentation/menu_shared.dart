import 'package:flutter/material.dart';

import '../../../core/navigation/commercial_tab_bus.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../models/menu_data.dart';

const Color menuCard = Color(0xFF062A58);
const Color menuCardDark = Color(0xFF05234D);
const Color menuLine = Color(0xFF2B527E);
const Color menuTextMuted = Color(0xFFB9C5DA);
const Color menuBlue = Color(0xFF1396FF);

DoubleMenuScale menuScaleOf(BuildContext context) {
  final width = MediaQuery.sizeOf(context).width;
  final scale = (width / 472).clamp(.78, 1.08).toDouble();
  return DoubleMenuScale(scale);
}

class DoubleMenuScale {
  const DoubleMenuScale(this.value);
  final double value;
  double call(double number) => number * value;
}

CommercialProfile menuHeaderProfile({
  required Map<String, dynamic> initialUser,
  MenuProfileData? loaded,
}) {
  if (loaded == null) return CommercialProfile.fromJson(initialUser);
  return CommercialProfile.fromJson({
    ...initialUser,
    'first_name': loaded.firstName,
    'name': loaded.name,
    'avatar_url': loaded.avatarUrl,
  });
}

void menuTabNavigate(BuildContext context, int index, {bool menuRoot = false}) {
  if (index == 4) {
    if (!menuRoot && Navigator.of(context).canPop()) Navigator.of(context).pop();
    return;
  }
  CommercialTabBus.request(index);
}

class MenuPageScaffold extends StatelessWidget {
  const MenuPageScaffold({
    super.key,
    required this.profile,
    required this.unreadNotifications,
    required this.body,
    this.menuRoot = false,
    this.showHeader = true,
    this.headerTitle,
    this.headerSubtitle,
    this.onNotificationsTap,
    this.onAvatarTap,
    this.onBack,
  });

  final CommercialProfile profile;
  final int unreadNotifications;
  final Widget body;
  final bool menuRoot;
  final bool showHeader;
  final String? headerTitle;
  final String? headerSubtitle;
  final VoidCallback? onNotificationsTap;
  final VoidCallback? onAvatarTap;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    final sc = menuScaleOf(context);
    final s = sc.call;
    return Scaffold(
      extendBody: true,
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: Column(
            children: [
              if (showHeader)
                Padding(
                  padding: EdgeInsets.fromLTRB(s(16), s(8), s(16), 0),
                  child: CommercialClientsHeader(
                    profile: profile,
                    unreadNotifications: unreadNotifications,
                    scale: sc.value,
                    onNotificationsTap: onNotificationsTap ?? () {},
                    onAvatarTap: onAvatarTap ?? () {},
                  ),
                )
              else if (headerTitle != null)
                Padding(
                  padding: EdgeInsets.fromLTRB(s(14), s(8), s(16), s(2)),
                  child: SizedBox(
                    height: s(74),
                    child: Row(
                      children: [
                        if (onBack != null)
                          IconButton(
                            onPressed: onBack,
                            icon: Icon(Icons.arrow_back_rounded, color: Colors.white, size: s(27)),
                          ),
                        Image.asset('assets/images/ovanie_logo.png', width: s(145), fit: BoxFit.contain),
                        Container(
                          width: 1,
                          height: s(40),
                          color: Colors.white24,
                          margin: EdgeInsets.symmetric(horizontal: s(12)),
                        ),
                        Expanded(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                headerTitle!,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(color: Colors.white, fontSize: s(17), fontWeight: FontWeight.w800),
                              ),
                              if (headerSubtitle != null) ...[
                                SizedBox(height: s(4)),
                                Text(
                                  headerSubtitle!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: TextStyle(color: menuTextMuted, fontSize: s(10.5)),
                                ),
                              ],
                            ],
                          ),
                        ),
                        _RoundHeaderIcon(
                          icon: Icons.notifications_none_rounded,
                          badge: unreadNotifications,
                          onTap: onNotificationsTap,
                          scale: sc.value,
                        ),
                        SizedBox(width: s(8)),
                        GestureDetector(
                          onTap: onAvatarTap,
                          child: _HeaderAvatar(profile: profile, scale: sc.value),
                        ),
                      ],
                    ),
                  ),
                ),
              Expanded(child: body),
            ],
          ),
        ),
      ),
      bottomNavigationBar: CommercialBottomNavigation(
        scale: sc.value,
        currentIndex: 4,
        onTap: (index) => menuTabNavigate(context, index, menuRoot: menuRoot),
      ),
    );
  }
}

class _RoundHeaderIcon extends StatelessWidget {
  const _RoundHeaderIcon({required this.icon, required this.badge, required this.onTap, required this.scale});
  final IconData icon;
  final int badge;
  final VoidCallback? onTap;
  final double scale;

  @override
  Widget build(BuildContext context) {
    double s(double v) => v * scale;
    return InkWell(
      onTap: onTap,
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
            child: Icon(icon, color: Colors.white, size: s(26)),
          ),
          if (badge > 0)
            Positioned(
              right: 0,
              top: s(-4),
              child: Container(
                constraints: BoxConstraints(minWidth: s(18), minHeight: s(18)),
                padding: EdgeInsets.symmetric(horizontal: s(4)),
                decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                alignment: Alignment.center,
                child: Text(
                  badge > 99 ? '99+' : '$badge',
                  style: TextStyle(color: Colors.white, fontSize: s(8.4), fontWeight: FontWeight.w800),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _HeaderAvatar extends StatelessWidget {
  const _HeaderAvatar({required this.profile, required this.scale});
  final CommercialProfile profile;
  final double scale;

  @override
  Widget build(BuildContext context) {
    double s(double v) => v * scale;
    final initials = profile.name
        .split(RegExp(r'\s+'))
        .where((e) => e.isNotEmpty)
        .take(2)
        .map((e) => e[0].toUpperCase())
        .join();
    return Container(
      width: s(49),
      height: s(49),
      padding: EdgeInsets.all(s(2)),
      decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: OvanieColors.blue)),
      child: ClipOval(
        child: profile.avatarUrl == null
            ? Container(
                color: const Color(0xFFC79B63),
                alignment: Alignment.center,
                child: Text(initials.isEmpty ? 'OC' : initials, style: TextStyle(color: Colors.white, fontSize: s(12), fontWeight: FontWeight.w800)),
              )
            : Image.network(
                profile.avatarUrl!,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => Container(
                  color: const Color(0xFFC79B63),
                  alignment: Alignment.center,
                  child: Text(initials.isEmpty ? 'OC' : initials, style: TextStyle(color: Colors.white, fontSize: s(12), fontWeight: FontWeight.w800)),
                ),
              ),
      ),
    );
  }
}

class MenuSearchBar extends StatelessWidget {
  const MenuSearchBar({
    super.key,
    required this.hint,
    this.controller,
    this.onChanged,
    this.onFilterTap,
    this.showFilterLabel = true,
  });

  final String hint;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onFilterTap;
  final bool showFilterLabel;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return Row(
      children: [
        Expanded(
          child: Container(
            height: s(52),
            decoration: BoxDecoration(
              color: const Color(0xFF092951).withOpacity(.86),
              borderRadius: BorderRadius.circular(s(13)),
              border: Border.all(color: const Color(0xFF2A517D)),
            ),
            child: Row(
              children: [
                SizedBox(width: s(13)),
                Icon(Icons.search_rounded, color: const Color(0xFFB6C3D9), size: s(25)),
                SizedBox(width: s(10)),
                Expanded(
                  child: TextField(
                    controller: controller,
                    onChanged: onChanged,
                    style: TextStyle(color: Colors.white, fontSize: s(12.4)),
                    decoration: InputDecoration(
                      border: InputBorder.none,
                      isDense: true,
                      hintText: hint,
                      hintStyle: TextStyle(color: const Color(0xFFB6C3D9), fontSize: s(11.8)),
                    ),
                  ),
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
            height: s(52),
            constraints: BoxConstraints(minWidth: showFilterLabel ? s(90) : s(50)),
            padding: EdgeInsets.symmetric(horizontal: showFilterLabel ? s(14) : s(12)),
            decoration: BoxDecoration(
              color: const Color(0xFF092951).withOpacity(.9),
              borderRadius: BorderRadius.circular(s(13)),
              border: Border.all(color: const Color(0xFF2A517D)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.tune_rounded, color: Colors.white, size: s(22)),
                if (showFilterLabel) ...[
                  SizedBox(width: s(7)),
                  Text('Filtres', style: TextStyle(color: Colors.white, fontSize: s(11.7), fontWeight: FontWeight.w600)),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class DarkMenuCard extends StatelessWidget {
  const DarkMenuCard({super.key, required this.child, this.padding, this.color, this.borderColor, this.radius = 14});
  final Widget child;
  final EdgeInsetsGeometry? padding;
  final Color? color;
  final Color? borderColor;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return Container(
      padding: padding ?? EdgeInsets.all(s(14)),
      decoration: BoxDecoration(
        color: color ?? menuCard.withOpacity(.8),
        borderRadius: BorderRadius.circular(s(radius)),
        border: Border.all(color: borderColor ?? menuLine.withOpacity(.95)),
      ),
      child: child,
    );
  }
}

class WhiteMenuCard extends StatelessWidget {
  const WhiteMenuCard({super.key, required this.child, this.padding, this.radius = 16});
  final Widget child;
  final EdgeInsetsGeometry? padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return Container(
      padding: padding ?? EdgeInsets.all(s(14)),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(s(radius)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(.14), blurRadius: s(12), offset: Offset(0, s(4)))],
      ),
      child: child,
    );
  }
}

class MenuSectionTitle extends StatelessWidget {
  const MenuSectionTitle(this.text, {super.key});
  final String text;
  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return Text(text, style: TextStyle(color: menuBlue, fontSize: s(13), fontWeight: FontWeight.w500));
  }
}

class MenuInfoStrip extends StatelessWidget {
  const MenuInfoStrip({super.key, required this.text, this.light = false});
  final String text;
  final bool light;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return Container(
      padding: EdgeInsets.symmetric(horizontal: s(14), vertical: s(10)),
      decoration: BoxDecoration(
        color: light ? const Color(0xFFEAF3FF) : const Color(0xFF062A58).withOpacity(.8),
        borderRadius: BorderRadius.circular(s(12)),
        border: Border.all(color: light ? const Color(0xFFD9E6F7) : const Color(0xFF25507C)),
      ),
      child: Row(
        children: [
          Icon(Icons.info_outline_rounded, color: menuBlue, size: s(21)),
          SizedBox(width: s(10)),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: light ? const Color(0xFF284978) : const Color(0xFFC3CEE1), fontSize: s(10.8), height: 1.3),
            ),
          ),
        ],
      ),
    );
  }
}

class MenuPageTitle extends StatelessWidget {
  const MenuPageTitle({super.key, required this.title, required this.subtitle, this.onBack, this.action});
  final String title;
  final String subtitle;
  final VoidCallback? onBack;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (onBack != null) ...[
          InkWell(onTap: onBack, child: Padding(padding: EdgeInsets.only(top: s(4), right: s(12)), child: Icon(Icons.arrow_back_rounded, color: Colors.white, size: s(28)))),
        ],
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: TextStyle(color: Colors.white, fontSize: s(28), fontWeight: FontWeight.w800, height: 1.05)),
              SizedBox(height: s(5)),
              Text(subtitle, style: TextStyle(color: const Color(0xFFC1CAD9), fontSize: s(11.5), height: 1.3)),
            ],
          ),
        ),
        if (action != null) ...[SizedBox(width: s(10)), action!],
      ],
    );
  }
}

class LoadingOrError extends StatelessWidget {
  const LoadingOrError({super.key, required this.loading, required this.error, required this.onRetry, required this.child});
  final bool loading;
  final String? error;
  final VoidCallback onRetry;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    if (loading) {
      return Padding(
        padding: EdgeInsets.all(s(32)),
        child: const Center(child: CircularProgressIndicator()),
      );
    }
    if (error != null) {
      return DarkMenuCard(
        child: Column(
          children: [
            Icon(Icons.cloud_off_rounded, color: OvanieColors.orange, size: s(34)),
            SizedBox(height: s(8)),
            Text(error!, textAlign: TextAlign.center, style: TextStyle(color: Colors.white, fontSize: s(11))),
            SizedBox(height: s(10)),
            OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      );
    }
    return child;
  }
}

String menuDate(DateTime? value, {bool withTime = true}) {
  if (value == null) return '—';
  final local = value.toLocal();
  const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  final date = '${local.day.toString().padLeft(2, '0')} ${months[local.month - 1]} ${local.year}';
  if (!withTime) return date;
  return '$date à ${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
}

String menuRelativeDate(DateTime? value) {
  if (value == null) return '—';
  final now = DateTime.now();
  final diff = now.difference(value.toLocal());
  if (diff.inMinutes >= 0 && diff.inMinutes < 60) return 'Il y a ${diff.inMinutes.clamp(1, 59)} min';
  final today = DateTime(now.year, now.month, now.day);
  final date = value.toLocal();
  final day = DateTime(date.year, date.month, date.day);
  if (day == today) return "Aujourd’hui • ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}";
  if (day == today.subtract(const Duration(days: 1))) return "Hier • ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}";
  return menuDate(value, withTime: false);
}
