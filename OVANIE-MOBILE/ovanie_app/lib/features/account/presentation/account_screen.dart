import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/push/push_notification_service.dart';
import '../../../core/utils/text_cleaner.dart';
import '../../addresses/presentation/addresses_screen.dart';
import '../../auth/data/auth_repository.dart';
import '../../auth/domain/session_store.dart';
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
        Widget support() => SupportCenterScreen(
              onOpenHome: onOpenHome,
              onOpenCategories: onOpenCategories,
              onOpenCart: onOpenCart,
              onOpenFavorites: onOpenFavorites,
            );

        return Scaffold(
          backgroundColor: OvanieColors.background,
          body: SafeArea(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
              children: [
                const Text(
                  'Mon compte',
                  style: TextStyle(color: OvanieColors.navy, fontSize: 24, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                authenticated
                    ? _ProfileHeader(
                        name: userName.isEmpty ? 'Mon compte' : userName,
                        detail: userPhone.isNotEmpty ? userPhone : userEmail,
                        onTap: () => _open(context, const ProfileScreen()),
                      )
                    : const _GuestCard(),
                const _SectionTitle('Mes achats'),
                _MenuGroup(children: [
                  _MenuRow(
                    icon: Icons.receipt_long_outlined,
                    title: 'Mes commandes',
                    onTap: () => _open(context, OrdersScreen(onOpenCart: onOpenCart, onStartShopping: onOpenCategories)),
                  ),
                  _MenuRow(
                    icon: Icons.assignment_return_outlined,
                    title: 'Retours et remboursements',
                    onTap: () => _open(context, const ReturnsScreen()),
                  ),
                  _MenuRow(
                    icon: Icons.favorite_border_rounded,
                    title: 'Mes favoris',
                    onTap: () => _openFavorites(context),
                  ),
                ]),
                const _SectionTitle('Paiement et livraison'),
                _MenuGroup(children: [
                  _MenuRow(
                    icon: Icons.location_on_outlined,
                    title: 'Mes adresses',
                    onTap: () => _open(context, const AddressesScreen()),
                  ),
                  _MenuRow(
                    icon: Icons.credit_card_outlined,
                    title: 'Moyens de paiement',
                    onTap: () => _open(context, const PaymentMethodsScreen()),
                  ),
                  _MenuRow(
                    icon: Icons.history_rounded,
                    title: 'Historique des paiements',
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
                ]),
                const _SectionTitle('Paramètres'),
                _MenuGroup(children: [
                  _MenuRow(
                    icon: Icons.notifications_none_rounded,
                    title: 'Notifications',
                    onTap: () => _open(context, const NotificationsScreen()),
                  ),
                  _MenuRow(
                    icon: Icons.headset_mic_outlined,
                    title: 'Aide et support',
                    onTap: () => _open(context, support(), auth: false),
                  ),
                ]),
                if (authenticated) ...[
                  const SizedBox(height: 20),
                  _MenuGroup(children: [
                    _MenuRow(
                      icon: Icons.logout_rounded,
                      title: 'Se déconnecter',
                      danger: true,
                      onTap: () => _logout(context),
                    ),
                  ]),
                ],
              ],
            ),
          ),
        );
      },
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.name, required this.detail, required this.onTap});

  final String name;
  final String detail;
  final VoidCallback onTap;

  String get _initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((e) => e.isNotEmpty).toList();
    if (parts.isEmpty) return 'O';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: OvanieColors.border),
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: OvanieColors.navy,
                child: Text(_initials, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.navy, fontSize: 17, fontWeight: FontWeight.w700)),
                    if (detail.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(detail, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: OvanieColors.muted, fontSize: 14)),
                    ],
                    const SizedBox(height: 2),
                    const Text('Voir mon profil', style: TextStyle(color: OvanieColors.blue, fontSize: 14, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: OvanieColors.muted),
            ],
          ),
        ),
      ),
    );
  }
}

class _GuestCard extends StatelessWidget {
  const _GuestCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: OvanieColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Connectez-vous',
            style: TextStyle(color: OvanieColors.navy, fontSize: 17, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          const Text(
            'Pour suivre vos commandes et gérer vos adresses.',
            style: TextStyle(color: OvanieColors.muted, fontSize: 14),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: SizedBox(
                  height: 46,
                  child: FilledButton(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LoginScreen())),
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('Se connecter', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: SizedBox(
                  height: 46,
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const RegisterScreen())),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: OvanieColors.navy,
                      side: const BorderSide(color: OvanieColors.border),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text('Créer un compte', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 22, 4, 8),
        child: Text(text, style: const TextStyle(color: OvanieColors.muted, fontSize: 13.5, fontWeight: FontWeight.w600)),
      );
}

class _MenuGroup extends StatelessWidget {
  const _MenuGroup({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: OvanieColors.border),
      ),
      child: Column(
        children: [
          for (var i = 0; i < children.length; i++) ...[
            children[i],
            if (i != children.length - 1) const Divider(height: 1, indent: 52, color: OvanieColors.border),
          ],
        ],
      ),
    );
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({required this.icon, required this.title, required this.onTap, this.danger = false});

  final IconData icon;
  final String title;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? OvanieColors.danger : OvanieColors.navy;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        child: Row(
          children: [
            Icon(icon, color: danger ? OvanieColors.danger : OvanieColors.blue, size: 22),
            const SizedBox(width: 16),
            Expanded(child: Text(title, style: TextStyle(color: color, fontSize: 15, fontWeight: FontWeight.w500))),
            if (!danger) const Icon(Icons.chevron_right_rounded, color: OvanieColors.muted, size: 22),
          ],
        ),
      ),
    );
  }
}
