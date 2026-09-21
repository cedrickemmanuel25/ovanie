import 'package:flutter/material.dart';
import '../../core/network/api_client.dart';
import '../menu/menu_ui.dart';
import '../after_sales/after_sales_screen.dart';
import '../orders/order_detail_screen.dart';
import '../products/product_detail_screen.dart';

class VendorNotificationsScreen extends StatefulWidget {
  const VendorNotificationsScreen({super.key});
  @override
  State<VendorNotificationsScreen> createState() => _NotificationsState();
}

String notificationCategory(Map<String, dynamic> n) {
  if (n['order_id'] != null) return 'Commandes';
  if (n['dispute_id'] != null) return 'Litiges';
  if (n['return_id'] != null) return 'Retours';
  final s = '${n['category']}'.toLowerCase();
  if (s.contains('payout') || s.contains('payment') || s.contains('finance'))
    return 'Finances';
  return 'Système';
}

IconData notificationIcon(Map<String, dynamic> n) =>
    switch (notificationCategory(n)) {
      'Commandes' => Icons.shopping_cart_outlined,
      'Finances' => Icons.payments_outlined,
      'Litiges' => Icons.balance,
      'Retours' => Icons.inventory_2_outlined,
      _ => Icons.notifications_outlined,
    };

class _NotificationsState extends State<VendorNotificationsScreen> {
  List<Map<String, dynamic>> rows = [];
  Map<String, dynamic> shop = {};
  bool loading = true;
  String? error;
  String query = '';
  int filter = 0;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      rows = await allMenuRows('notifications');
      shop = mapOf((await menuApi('shop'))['shop']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    const filters = [
      'Toutes',
      'Non lues',
      'Commandes',
      'Finances',
      'Litiges',
      'Retours',
    ];
    final selected = rows
        .where(
          (n) =>
              '$n'.toLowerCase().contains(query.toLowerCase()) &&
              (filter == 0 ||
                  filter == 1 && n['read'] != true ||
                  filter > 1 && notificationCategory(n) == filters[filter]),
        )
        .toList();
    return MenuPage(
      title: 'Notifications',
      subtitle: 'Consultez vos alertes, mises à jour et activités récentes',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        ShopIdentity(shop: shop),
        MenuMetrics(
          items: [
            (
              'Toutes',
              '${rows.length}',
              Icons.notifications_outlined,
              menuBlue,
            ),
            (
              'Non lues',
              '${rows.where((n) => n['read'] != true).length}',
              Icons.mail_outline,
              menuOrange,
            ),
            (
              'Commandes',
              '${rows.where((n) => notificationCategory(n) == 'Commandes').length}',
              Icons.assignment_outlined,
              menuBlue,
            ),
            (
              'Système',
              '${rows.where((n) => notificationCategory(n) == 'Système').length}',
              Icons.settings_outlined,
              menuMuted,
            ),
          ],
        ),
        menuSearch(
          (v) => setState(() => query = v),
          'Rechercher une notification…',
        ),
        menuFilters(filters, filter, (v) => setState(() => filter = v)),
        menuHeading('Liste des notifications'),
        const SizedBox(height: 10),
        if (selected.isEmpty) menuEmpty('Aucune notification correspondante'),
        for (final n in selected)
          DataCard(
            child: InkWell(
              onTap: () async {
                await Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => NotificationDetailScreen(id: '${n['id']}'),
                  ),
                );
                if (mounted) await load();
              },
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  children: [
                    menuIcon(notificationIcon(n)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          menuHeading(screenText(n['title'])),
                          Text(
                            screenText(n['message']),
                            style: const TextStyle(
                              color: menuMuted,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            dateLabel(n['created_at']),
                            style: const TextStyle(
                              color: menuMuted,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      Icons.circle,
                      size: 8,
                      color: n['read'] == true ? menuMuted : Colors.blue,
                    ),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class NotificationDetailScreen extends StatefulWidget {
  const NotificationDetailScreen({super.key, required this.id});
  final String id;
  @override
  State<NotificationDetailScreen> createState() => _NotificationState();
}

class _NotificationState extends State<NotificationDetailScreen> {
  Map<String, dynamic> data = {}, shop = {};
  bool loading = true, busy = false;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final r = await Future.wait([
        menuApi('notifications/${widget.id}'),
        menuApi('shop'),
      ]);
      data = mapOf(r[0]['notification']);
      shop = mapOf(r[1]['shop']);
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> read() async {
    setState(() => busy = true);
    try {
      await menuApi('notifications/${widget.id}/read', method: 'POST');
      await load();
    } catch (e) {
      if (mounted) menuError(context, e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Widget? destination() {
    if (data['order_id'] != null)
      return OrderDetailScreen(orderId: number(data['order_id']).toInt());
    if (data['product_id'] != null)
      return ProductDetailScreen(productId: number(data['product_id']).toInt());
    if (data['return_id'] != null)
      return CaseDetailScreen(id: number(data['return_id']).toInt());
    if (data['dispute_id'] != null)
      return CaseDetailScreen(
        id: number(data['dispute_id']).toInt(),
        disputes: true,
      );
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final details = mapOf(data['details']);
    final target = destination();
    return MenuPage(
      title: 'Détail de la notification',
      subtitle: 'Consultez le contenu complet de cette alerte',
      refresh: load,
      loading: loading,
      error: error,
      children: [
        ShopIdentity(shop: shop),
        DataCard(
          child: Column(
            children: [
              dataLine('Type', notificationCategory(data)),
              dataLine('Date', dateLabel(data['created_at'])),
              dataLine('Statut', data['read'] == true ? 'Lue' : 'Non lue'),
            ],
          ),
        ),
        DataCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              menuIcon(notificationIcon(data)),
              const SizedBox(height: 10),
              menuHeading(screenText(data['title'])),
              const SizedBox(height: 10),
              Text(
                screenText(data['message']),
                style: const TextStyle(
                  color: menuBlue,
                  fontSize: 15,
                  height: 1.5,
                ),
              ),
              if (details['description'] != null)
                Text(
                  '${details['description']}',
                  style: const TextStyle(color: menuMuted),
                ),
              const SizedBox(height: 12),
              if (details['priority'] != null)
                dataLine('Priorité', screenText(details['priority'])),
              if (details['client_name'] != null)
                dataLine('Client', screenText(details['client_name'])),
              if (details['amount'] != null)
                dataLine('Montant', screenMoney(details['amount'])),
              dataLine('Source', notificationCategory(data)),
            ],
          ),
        ),
        DataCard(
          title: 'Historique',
          icon: Icons.history,
          child: Column(
            children: [
              dataLine('Notification générée', dateLabel(data['created_at'])),
              if (data['read_at'] != null)
                dataLine('Notification lue', dateLabel(data['read_at']))
              else
                const Text(
                  'En attente de lecture',
                  style: TextStyle(color: menuMuted),
                ),
            ],
          ),
        ),
        if (target != null)
          DataCard(
            title: 'Actions recommandées',
            icon: Icons.check_circle_outline,
            child: ListTile(
              title: const Text('Consulter le dossier concerné'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => openMenuPage(context, target),
            ),
          ),
        dataPair(
          menuButton(
            data['read'] == true ? 'Déjà lue' : 'Marquer comme lue',
            busy || data['read'] == true ? null : read,
            outlined: true,
            icon: Icons.done_all,
          ),
          ifTarget(target),
        ),
      ],
    );
  }

  Widget ifTarget(Widget? target) => target == null
      ? const SizedBox.shrink()
      : menuButton(
          data['order_id'] != null ? 'Voir la commande' : 'Voir le dossier',
          () => openMenuPage(context, target),
          icon: Icons.arrow_forward,
        );
}
