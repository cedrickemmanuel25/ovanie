import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

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
import '../../payments/presentation/payment_ui.dart';
import '../../recent/presentation/recently_viewed_screen.dart';
import '../../returns/presentation/returns_screen.dart';
import '../../reviews/presentation/reviews_screen.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/client_account_repository.dart';
import 'legal_documents_screen.dart';
import 'loyalty_screen.dart';
import 'notifications_screen.dart';
import 'payment_methods_screen.dart';
import 'profile_screen.dart';

const _rowText = Color(0xFF16233A);
const _rowIcon = Color(0xFF2E3A4D);
const _sectionText = Color(0xFF3B4A60);

class AccountScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenFavorites;
  final VoidCallback? onOpenCart;

  const AccountScreen({super.key, this.onOpenHome, this.onOpenCategories, this.onOpenFavorites, this.onOpenCart});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  int _unread = 0;
  bool? _lastAuthenticated;

  @override
  void initState() {
    super.initState();
    SessionStore.instance.addListener(_onSessionChanged);
    _onSessionChanged();
  }

  @override
  void dispose() {
    SessionStore.instance.removeListener(_onSessionChanged);
    super.dispose();
  }

  void _onSessionChanged() {
    final authenticated = SessionStore.instance.isAuthenticated;
    if (authenticated == _lastAuthenticated) return;
    _lastAuthenticated = authenticated;
    if (authenticated) {
      _refreshUnread();
    } else if (_unread != 0 && mounted) {
      setState(() => _unread = 0);
    }
  }

  /// Pastille de la cloche : nombre de notifications non lues côté serveur.
  Future<void> _refreshUnread() async {
    if (!SessionStore.instance.isAuthenticated) return;
    try {
      final page = await const ClientAccountRepository().notifications();
      if (mounted) setState(() => _unread = page.unreadCount);
    } catch (_) {
      // La pastille est secondaire : on garde la dernière valeur connue.
    }
  }

  bool _ensureAuthenticated() {
    if (SessionStore.instance.isAuthenticated) return true;
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const LoginScreen()));
    return false;
  }

  Future<void> _open(Widget screen, {bool auth = true}) async {
    if (auth && !_ensureAuthenticated()) return;
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
    _refreshUnread();
  }

  void _openFavorites() {
    if (!_ensureAuthenticated()) return;
    if (widget.onOpenFavorites != null) {
      widget.onOpenFavorites!();
      return;
    }
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const FavoritesScreen()));
  }

  Future<void> _logout() async {
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

    if (mounted) showOvanieToast(context, 'Vous êtes déconnecté.');
  }

  Widget get _notifications => NotificationsScreen(
    onOpenHome: widget.onOpenHome,
    onOpenCategories: widget.onOpenCategories,
    onOpenCart: widget.onOpenCart,
    onOpenFavorites: widget.onOpenFavorites,
  );

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: SessionStore.instance,
      builder: (context, _) {
        final authenticated = SessionStore.instance.isAuthenticated;
        final userName = cleanOvanieText(SessionStore.instance.name ?? '').trim();
        final userEmail = (SessionStore.instance.email ?? '').trim();
        final userPhone = (SessionStore.instance.phone ?? '').trim();
        final top = MediaQuery.paddingOf(context).top;
        // Pour un visiteur, les rubriques restent visibles mais atténuées :
        // un appui renvoie vers la connexion.
        final enabled = authenticated;

        return AnnotatedRegion<SystemUiOverlayStyle>(
          value: SystemUiOverlayStyle.light,
          child: Scaffold(
            backgroundColor: OvanieColors.background,
            // Bande marine fixe sous la barre d'état : le contenu défilant ne
            // passe jamais sous l'heure et les icônes système.
            body: Stack(
              children: [
                RefreshIndicator(
                  color: OvanieColors.orange,
                  onRefresh: _refreshUnread,
                  child: ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      Stack(
                        children: [
                          Container(
                            height: top + 112,
                            decoration: const BoxDecoration(
                              color: OvanieColors.navy,
                              borderRadius: BorderRadius.vertical(bottom: Radius.circular(26)),
                            ),
                          ),
                          Padding(
                            padding: EdgeInsets.fromLTRB(18, top + 20, 10, 0),
                            child: Row(
                              children: [
                                const Expanded(
                                  child: Text(
                                    'Mon compte',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 26,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: -0.6,
                                    ),
                                  ),
                                ),
                                _BellButton(showDot: authenticated && _unread > 0, onTap: () => _open(_notifications)),
                              ],
                            ),
                          ),
                          Padding(
                            padding: EdgeInsets.fromLTRB(16, top + 78, 16, 0),
                            child: authenticated
                                ? _ProfileCard(
                                    name: userName.isEmpty ? 'Client OVANIE' : userName,
                                    detail: userEmail.isNotEmpty ? userEmail : userPhone,
                                    onTap: () => _open(
                                      ProfileScreen(
                                        onOpenHome: widget.onOpenHome,
                                        onOpenCategories: widget.onOpenCategories,
                                        onOpenCart: widget.onOpenCart,
                                        onOpenFavorites: widget.onOpenFavorites,
                                      ),
                                    ),
                                  )
                                : const _GuestCard(),
                          ),
                        ],
                      ),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _SectionTitle('Mes achats', enabled: enabled),
                            _MenuGroup(
                              enabled: enabled,
                              children: [
                                _MenuRow(
                                  icon: Icons.shopping_bag_outlined,
                                  title: 'Mes commandes',
                                  onTap: () => _open(
                                    OrdersScreen(
                                      onOpenCart: widget.onOpenCart,
                                      onStartShopping: widget.onOpenCategories,
                                    ),
                                  ),
                                ),
                                _MenuRow(
                                  icon: Icons.inventory_2_outlined,
                                  title: 'Retours et remboursements',
                                  onTap: () => _open(const ReturnsScreen()),
                                ),
                                _MenuRow(
                                  icon: Icons.favorite_border_rounded,
                                  title: 'Mes favoris',
                                  onTap: _openFavorites,
                                ),
                                _MenuRow(
                                  icon: Icons.star_border_rounded,
                                  title: 'Notes et avis',
                                  onTap: () => _open(const ReviewsScreen()),
                                ),
                                _MenuRow(
                                  icon: Icons.schedule_rounded,
                                  title: 'Vu récemment',
                                  onTap: () => _open(RecentlyViewedScreen(onOpenCategories: widget.onOpenCategories)),
                                ),
                              ],
                            ),
                            _SectionTitle('Paiement et livraison', enabled: enabled),
                            _MenuGroup(
                              enabled: enabled,
                              children: [
                                _MenuRow(
                                  icon: Icons.location_on_outlined,
                                  title: 'Mes adresses',
                                  onTap: () => _open(const AddressesScreen()),
                                ),
                                _MenuRow(
                                  icon: Icons.payment_outlined,
                                  title: 'Moyens de paiement',
                                  onTap: () => _open(const PaymentMethodsScreen()),
                                ),
                                _MenuRow(
                                  icon: Icons.receipt_outlined,
                                  title: 'Historique des paiements',
                                  onTap: () => _open(
                                    PaymentHistoryScreen(
                                      onOpenHome: widget.onOpenHome,
                                      onOpenCategories: widget.onOpenCategories,
                                      onOpenCart: widget.onOpenCart,
                                      onOpenFavorites: widget.onOpenFavorites,
                                    ),
                                  ),
                                ),
                                _MenuRow(
                                  icon: Icons.workspace_premium_outlined,
                                  title: 'Points & avantages',
                                  onTap: () => _open(const LoyaltyScreen()),
                                ),
                              ],
                            ),
                            _SectionTitle('Paramètres', enabled: enabled),
                            _MenuGroup(
                              enabled: enabled,
                              children: [
                                _MenuRow(
                                  icon: Icons.notifications_none_rounded,
                                  title: 'Notifications',
                                  onTap: () => _open(_notifications),
                                ),
                                // L'aide reste accessible sans compte.
                                _MenuRow(
                                  icon: Icons.help_outline_rounded,
                                  title: 'Aide et support',
                                  onTap: () => _open(
                                    SupportCenterScreen(
                                      onOpenHome: widget.onOpenHome,
                                      onOpenCategories: widget.onOpenCategories,
                                      onOpenCart: widget.onOpenCart,
                                      onOpenFavorites: widget.onOpenFavorites,
                                    ),
                                    auth: false,
                                  ),
                                ),
                                _MenuRow(
                                  icon: Icons.gavel_rounded,
                                  title: 'CGU & politiques',
                                  onTap: () => _open(const LegalDocumentsScreen(), auth: false),
                                ),
                              ],
                            ),
                            if (authenticated) ...[
                              const SizedBox(height: 14),
                              _MenuGroup(
                                enabled: true,
                                children: [
                                  _MenuRow(
                                    icon: Icons.logout_rounded,
                                    title: 'Se déconnecter',
                                    danger: true,
                                    onTap: _logout,
                                  ),
                                ],
                              ),
                            ],
                            const SizedBox(height: 14),
                            const Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.verified_user_rounded, size: 15, color: Color(0xFF5B6B82)),
                                SizedBox(width: 6),
                                Text(
                                  'Compte client sécurisé',
                                  style: TextStyle(color: OvanieColors.muted, fontSize: 12.5),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  height: top,
                  child: const ColoredBox(color: OvanieColors.navy),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _BellButton extends StatelessWidget {
  const _BellButton({required this.showDot, required this.onTap});

  final bool showDot;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      onPressed: onTap,
      tooltip: 'Notifications',
      icon: Stack(
        clipBehavior: Clip.none,
        children: [
          const Icon(Icons.notifications_none_rounded, color: Colors.white, size: 28),
          if (showDot)
            Positioned(
              right: -1,
              top: -1,
              child: Container(
                width: 11,
                height: 11,
                decoration: BoxDecoration(
                  color: OvanieColors.orange,
                  shape: BoxShape.circle,
                  border: Border.all(color: OvanieColors.navy, width: 1.5),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

BoxDecoration _cardDecoration() => BoxDecoration(
  color: Colors.white,
  borderRadius: BorderRadius.circular(14),
  boxShadow: const [BoxShadow(color: Color(0x14102A4C), blurRadius: 14, offset: Offset(0, 4))],
);

class _ProfileCard extends StatelessWidget {
  const _ProfileCard({required this.name, required this.detail, required this.onTap});

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
    return DecoratedBox(
      decoration: _cardDecoration(),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
            child: Row(
              children: [
                Container(
                  width: 62,
                  height: 62,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                  child: Text(
                    _initials,
                    style: const TextStyle(color: Colors.white, fontSize: 23, fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: OvanieColors.navy, fontSize: 17, fontWeight: FontWeight.w800),
                      ),
                      if (detail.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(
                          detail,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: OvanieColors.muted, fontSize: 13.5),
                        ),
                      ],
                      const SizedBox(height: 5),
                      const Row(
                        children: [
                          Text(
                            'Voir mon profil',
                            style: TextStyle(color: OvanieColors.orange, fontSize: 14, fontWeight: FontWeight.w700),
                          ),
                          SizedBox(width: 2),
                          Icon(Icons.chevron_right_rounded, color: OvanieColors.orange, size: 18),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
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
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: 62,
                height: 62,
                decoration: const BoxDecoration(color: Color(0xFFEFF2F6), shape: BoxShape.circle),
                child: const Icon(Icons.person_outline_rounded, color: Color(0xFF4A586E), size: 34),
              ),
              const SizedBox(width: 16),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Connectez-vous',
                      style: TextStyle(color: OvanieColors.navy, fontSize: 17, fontWeight: FontWeight.w800),
                    ),
                    SizedBox(height: 3),
                    Text(
                      'Pour suivre vos commandes et gérer vos adresses.',
                      style: TextStyle(color: OvanieColors.muted, fontSize: 13.5, height: 1.35),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
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
          const SizedBox(height: 10),
          SizedBox(
            height: 46,
            child: OutlinedButton(
              onPressed: () =>
                  Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const RegisterScreen())),
              style: OutlinedButton.styleFrom(
                foregroundColor: OvanieColors.navy,
                side: const BorderSide(color: Color(0xFFC9D2DE)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: const Text('Créer un compte', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text, {required this.enabled});

  final String text;
  final bool enabled;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(2, 22, 2, 9),
    child: Text(
      text,
      style: TextStyle(
        color: enabled ? _sectionText : _sectionText.withValues(alpha: 0.5),
        fontSize: 15,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _MenuGroup extends StatelessWidget {
  const _MenuGroup({required this.children, required this.enabled});

  final List<Widget> children;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: const [BoxShadow(color: Color(0x0A102A4C), blurRadius: 8, offset: Offset(0, 2))],
      ),
      child: Opacity(
        opacity: enabled ? 1 : 0.45,
        child: Column(
          children: [
            for (var i = 0; i < children.length; i++) ...[
              children[i],
              if (i != children.length - 1) const Divider(height: 1, thickness: 1, color: Color(0xFFEDF1F6)),
            ],
          ],
        ),
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
    final color = danger ? OvanieColors.danger : _rowText;
    return InkWell(
      onTap: onTap,
      child: SizedBox(
        height: 50,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Row(
            children: [
              Icon(icon, color: danger ? OvanieColors.danger : _rowIcon, size: 23),
              const SizedBox(width: 18),
              Expanded(
                child: Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(color: color, fontSize: 15.5, fontWeight: FontWeight.w500),
                ),
              ),
              if (!danger) const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8), size: 22),
            ],
          ),
        ),
      ),
    );
  }
}
