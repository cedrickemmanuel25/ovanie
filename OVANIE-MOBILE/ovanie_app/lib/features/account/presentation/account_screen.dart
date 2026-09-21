import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/push/push_notification_service.dart';
import '../../../core/utils/text_cleaner.dart';
import '../../addresses/presentation/addresses_screen.dart';
import '../../auth/data/auth_repository.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart';
import '../../auth/presentation/login_screen.dart';
import '../../auth/presentation/register_screen.dart';
import '../../cart/domain/cart_store.dart';
import '../../favorites/domain/favorites_store.dart';
import '../../favorites/presentation/favorites_screen.dart';
import '../../orders/presentation/orders_screen.dart';
import '../../payments/presentation/payment_history_screen.dart';
import '../../returns/presentation/returns_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import 'notifications_screen.dart';
import 'payment_methods_screen.dart';
import 'profile_screen.dart';

class AccountScreen extends StatelessWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenFavorites;
  final VoidCallback? onOpenCart;

  const AccountScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenFavorites,
    this.onOpenCart,
  });

  bool _ensureAuthenticated(BuildContext context) {
    if (SessionStore.instance.isAuthenticated) return true;
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LoginScreen()));
    return false;
  }

  void _open(BuildContext context, Widget screen, {bool auth = true}) {
    if (auth && !_ensureAuthenticated(context)) return;
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  void _openFavorites(BuildContext context) {
    if (!_ensureAuthenticated(context)) return;
    if (onOpenFavorites != null) {
      onOpenFavorites!();
      return;
    }
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const FavoritesScreen()));
  }

  Future<void> _logout(BuildContext context) async {
    final token = SessionStore.instance.token;
    try {
      await PushNotificationService.instance.unregisterCurrentDevice();
    } catch (_) {}
    try {
      await CartStore.instance.flushPendingServerMutations();
    } catch (_) {}
    await CartStore.instance.clearLocalMirrorOnly();
    FavoritesStore.instance.clearLocalOnly();
    await SessionStore.instance.clear();

    if (token != null && token.isNotEmpty) {
      try {
        await const AuthRepository().logout(token);
      } catch (_) {}
    }

    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vous êtes déconnecté.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: SessionStore.instance,
      builder: (context, _) {
        final authenticated = SessionStore.instance.isAuthenticated;
        final userName = cleanOvanieText(SessionStore.instance.name ?? '').trim();
        final userEmail = (SessionStore.instance.email ?? '').trim();
        final userPhone = (SessionStore.instance.phone ?? '').trim();

        return Scaffold(
          backgroundColor: Colors.white,
          body: SafeArea(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                const Text(
                  'Compte',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 27, fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                authenticated
                    ? _AuthenticatedBanner(
                        name: userName.isEmpty ? 'Client OVANIE' : userName,
                        email: userEmail,
                        phone: userPhone,
                        onProfile: () => _open(context, const ProfileScreen()),
                      )
                    : const _GuestBanner(),
                const SizedBox(height: 18),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE0E6F0)),
                  ),
                  child: Column(
                    children: [
                      _MenuRow(
                        icon: Icons.receipt_long_outlined,
                        tint: const Color(0xFF6558F5),
                        background: const Color(0xFFF2F0FF),
                        title: 'Mes commandes',
                        subtitle: 'Suivez et gérez toutes vos commandes',
                        onTap: () => _open(
                          context,
                          OrdersScreen(onOpenCart: onOpenCart, onStartShopping: onOpenCategories),
                        ),
                      ),
                      _MenuRow(
                        icon: Icons.sync_rounded,
                        tint: const Color(0xFFFF8A00),
                        background: const Color(0xFFFFF4E5),
                        title: 'Mes retours & remboursements',
                        subtitle: 'Consultez vos retours et remboursements',
                        onTap: () => _open(context, const ReturnsScreen()),
                      ),
                      _MenuRow(
                        icon: Icons.favorite_border_rounded,
                        tint: const Color(0xFFF04444),
                        background: const Color(0xFFFFEEEE),
                        title: 'Mes favoris',
                        subtitle: 'Vos produits favoris',
                        onTap: () => _openFavorites(context),
                      ),
                      _MenuRow(
                        icon: Icons.location_on_outlined,
                        tint: const Color(0xFF14A650),
                        background: const Color(0xFFECFAF1),
                        title: 'Mes adresses',
                        subtitle: 'Gérez vos adresses de livraison',
                        onTap: () => _open(context, const AddressesScreen()),
                      ),
                      _MenuRow(
                        icon: Icons.credit_card_outlined,
                        tint: const Color(0xFFFF8A00),
                        background: const Color(0xFFFFF5E8),
                        title: 'Mes moyens de paiement',
                        subtitle: 'Cartes, mobile money et autres',
                        onTap: () => _open(context, const PaymentMethodsScreen()),
                      ),
                      _MenuRow(
                        icon: Icons.account_balance_wallet_outlined,
                        tint: const Color(0xFF1464EB),
                        background: const Color(0xFFEEF4FF),
                        title: 'Historique paiement',
                        subtitle: 'Consultez vos paiements et reçus',
                        onTap: () => _open(
                          context,
                          PaymentHistoryScreen(
                            onOpenHome: onOpenHome,
                            onOpenCategories: onOpenCategories,
                            onOpenCart: onOpenCart,
                            onOpenFavorites: onOpenFavorites,
                          ),
                        ),
                      ),
                      _MenuRow(
                        icon: Icons.notifications_none_rounded,
                        tint: const Color(0xFF7038F5),
                        background: const Color(0xFFF4F0FF),
                        title: 'Notifications',
                        subtitle: 'Messages, promotions et alertes',
                        onTap: () => _open(context, const NotificationsScreen()),
                      ),
                      _MenuRow(
                        icon: Icons.person_outline_rounded,
                        tint: const Color(0xFF1464EB),
                        background: const Color(0xFFEEF4FF),
                        title: 'Mon profil',
                        subtitle: 'Gérez vos informations personnelles',
                        onTap: () => _open(context, const ProfileScreen()),
                      ),
                      _MenuRow(
                        icon: Icons.headset_mic_outlined,
                        tint: const Color(0xFF1464EB),
                        background: const Color(0xFFEEF4FF),
                        title: 'Aide & Support',
                        subtitle: 'FAQ, contact et assistance',
                        divider: false,
                        onTap: () => _open(context, SupportCenterScreen(
                            onOpenHome: onOpenHome,
                            onOpenCategories: onOpenCategories,
                            onOpenCart: onOpenCart,
                            onOpenFavorites: onOpenFavorites,
                          ), auth: false),
                      ),
                    ],
                  ),
                ),
                if (authenticated) ...[
                  const SizedBox(height: 18),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFBFCFF),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFDCE4F0)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: const BoxDecoration(color: Color(0xFFF0F4FF), shape: BoxShape.circle),
                          child: const Icon(Icons.help_outline_rounded, color: OvanieColors.blue),
                        ),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Besoin d’aide ?', style: TextStyle(color: OvanieColors.navy, fontSize: 13, fontWeight: FontWeight.w900)),
                              SizedBox(height: 3),
                              Text('Notre équipe est là pour vous accompagner.', style: TextStyle(color: Color(0xFF596A99), fontSize: 11.5)),
                            ],
                          ),
                        ),
                        FilledButton(
                          onPressed: () => _open(context, SupportCenterScreen(
                            onOpenHome: onOpenHome,
                            onOpenCategories: onOpenCategories,
                            onOpenCart: onOpenCart,
                            onOpenFavorites: onOpenFavorites,
                          ), auth: false),
                          style: FilledButton.styleFrom(
                            backgroundColor: OvanieColors.orange,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                          ),
                          child: const Text('Nous contacter', style: TextStyle(fontWeight: FontWeight.w800)),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    height: 48,
                    child: OutlinedButton.icon(
                      onPressed: () => _logout(context),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: const Color(0xFFE52626),
                        side: const BorderSide(color: Color(0xFFE52626)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                      ),
                      icon: const Icon(Icons.logout_rounded),
                      label: const Text('Déconnexion', style: TextStyle(fontWeight: FontWeight.w900)),
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

class _GuestBanner extends StatelessWidget {
  const _GuestBanner();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final leftWidth = (width * .62).clamp(184.0, 330.0).toDouble();
        final logoWidth = (width * .34).clamp(104.0, 180.0).toDouble();

        return Container(
          height: 198,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(17),
            gradient: const LinearGradient(
              colors: [Color(0xFF06196C), Color(0xFF071F58)],
            ),
          ),
          clipBehavior: Clip.antiAlias,
          child: Stack(
            children: [
              const Positioned.fill(
                child: CustomPaint(painter: _AccountConstructionPainter()),
              ),
              Positioned(
                left: 18,
                top: 20,
                width: leftWidth,
                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Connectez-vous pour\nune expérience personnalisée',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 17.5,
                        height: 1.15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Suivez vos commandes, gérez vos adresses, enregistrez vos favoris et bien plus encore.',
                      maxLines: 4,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Color(0xFFE2E8FF),
                        fontSize: 10.3,
                        height: 1.32,
                      ),
                    ),
                  ],
                ),
              ),
              Positioned(
                left: 18,
                bottom: 18,
                width: leftWidth,
                child: Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 38,
                        child: FilledButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => const LoginScreen(),
                            ),
                          ),
                          style: FilledButton.styleFrom(
                            backgroundColor: OvanieColors.orange,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: const FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              'Se connecter',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 9),
                    Expanded(
                      child: SizedBox(
                        height: 38,
                        child: OutlinedButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => const RegisterScreen(),
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(color: Colors.white),
                            padding: const EdgeInsets.symmetric(horizontal: 6),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: const FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              'Créer un compte',
                              style: TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Positioned(
                right: 14,
                top: 58,
                width: logoWidth,
                child: const Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    OvanieWordmark(fontSize: 29, lightColor: Colors.white),
                    SizedBox(height: 5),
                    Text(
                      'Votre chantier, notre priorité',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 8.2,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _AuthenticatedBanner extends StatelessWidget {
  final String name;
  final String email;
  final String phone;
  final VoidCallback onProfile;

  const _AuthenticatedBanner({required this.name, required this.email, required this.phone, required this.onProfile});

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty).toList();
    if (parts.isEmpty) return 'OV';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 177,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(17),
        gradient: const LinearGradient(colors: [Color(0xFF06196C), Color(0xFF071F58)]),
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          const Positioned.fill(child: CustomPaint(painter: _AccountConstructionPainter())),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Row(
              children: [
                Expanded(
                  flex: 7,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          CircleAvatar(
                            radius: 30,
                            backgroundColor: Colors.white,
                            child: Text(initials, style: const TextStyle(color: OvanieColors.navy, fontSize: 22, fontWeight: FontWeight.w900)),
                          ),
                          const SizedBox(width: 13),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
                                const SizedBox(height: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                                  decoration: BoxDecoration(color: OvanieColors.orange, borderRadius: BorderRadius.circular(6)),
                                  child: const Text('Client', style: TextStyle(color: Colors.white, fontSize: 9.5, fontWeight: FontWeight.w800)),
                                ),
                                if (phone.isNotEmpty) ...[
                                  const SizedBox(height: 7),
                                  Text('☎  $phone', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFFE2E8FF), fontSize: 10)),
                                ],
                                if (email.isNotEmpty) ...[
                                  const SizedBox(height: 4),
                                  Text('✉  $email', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Color(0xFFE2E8FF), fontSize: 10)),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                      const Spacer(),
                      SizedBox(
                        width: double.infinity,
                        height: 36,
                        child: OutlinedButton(
                          onPressed: onProfile,
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(color: Colors.white),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                          ),
                          child: const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text('Voir mon profil', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700)),
                              SizedBox(width: 8),
                              Icon(Icons.chevron_right_rounded, size: 18),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  flex: 4,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      OvanieWordmark(fontSize: 31, lightColor: Colors.white),
                      SizedBox(height: 5),
                      Text('Votre chantier, notre priorité', textAlign: TextAlign.center, style: TextStyle(color: Colors.white, fontSize: 8.5, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _MenuRow extends StatelessWidget {
  final IconData icon;
  final Color tint;
  final Color background;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final bool divider;

  const _MenuRow({
    required this.icon,
    required this.tint,
    required this.background,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.divider = true,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(11)),
                  child: Icon(icon, color: tint, size: 23),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: const TextStyle(color: OvanieColors.navy, fontSize: 13.2, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 4),
                      Text(subtitle, style: const TextStyle(color: Color(0xFF596A99), fontSize: 11.2, height: 1.2)),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: OvanieColors.navy, size: 24),
              ],
            ),
          ),
        ),
        if (divider) const Divider(height: 1, color: Color(0xFFE8ECF3)),
      ],
    );
  }
}

class _AccountConstructionPainter extends CustomPainter {
  const _AccountConstructionPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()
      ..color = Colors.white.withOpacity(.14)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;

    final start = size.width * .56;
    final base = size.height - 5;
    for (var i = 0; i < 5; i++) {
      final x = start + i * 24;
      final h = 44.0 + (i % 3) * 13;
      canvas.drawRect(Rect.fromLTWH(x, base - h, 20, h), p);
    }
    final craneX = size.width * .73;
    canvas.drawLine(Offset(craneX, 25), Offset(craneX, base), p);
    canvas.drawLine(Offset(craneX - 38, 25), Offset(size.width - 16, 25), p);
    canvas.drawLine(Offset(size.width - 50, 25), Offset(size.width - 50, 58), p);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
