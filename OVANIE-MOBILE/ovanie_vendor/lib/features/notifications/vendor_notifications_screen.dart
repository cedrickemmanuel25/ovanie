import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/sync/live_sync_service.dart';
import '../after_sales/after_sales_screen.dart';
import '../menu/menu_ui.dart';
import '../orders/order_detail_screen.dart';
import '../products/product_detail_screen.dart';

class VendorNotificationsScreen extends StatefulWidget {
  const VendorNotificationsScreen({super.key});
  @override
  State<VendorNotificationsScreen> createState() => _NotificationsState();
}

String notificationCategory(Map<String, dynamic> n) {
  final text = '${n['category']} ${n['title']}'.toLowerCase();
  if (n['dispute_id'] != null || text.contains('litige')) return 'Litiges';
  if (n['return_id'] != null || text.contains('retour') || text.contains('réclamation')) {
    return 'Retours';
  }
  if (text.contains('payout') ||
      text.contains('payment') ||
      text.contains('versement') ||
      text.contains('paiement') ||
      text.contains('réception confirmée')) {
    return 'Finances';
  }
  if (n['order_id'] != null || text.contains('commande') || text.contains('livr')) {
    return 'Commandes';
  }
  return 'Système';
}

IconData notificationIcon(Map<String, dynamic> n) => switch (notificationCategory(n)) {
  'Commandes' => Icons.shopping_cart_outlined,
  'Finances' => Icons.payments_outlined,
  'Litiges' => Icons.balance_rounded,
  'Retours' => Icons.inventory_2_outlined,
  _ => Icons.notifications_none_rounded,
};

Color notificationColor(Map<String, dynamic> n) => switch (notificationCategory(n)) {
  'Commandes' => menuAzure,
  'Finances' => menuGreen,
  'Litiges' => menuRed,
  'Retours' => menuOrange,
  _ => Mk.icon,
};

Widget? notificationTarget(Map<String, dynamic> n) {
  if (n['order_id'] != null) {
    return OrderDetailScreen(orderId: number(n['order_id']).toInt());
  }
  if (n['product_id'] != null) {
    return ProductDetailScreen(productId: number(n['product_id']).toInt());
  }
  if (n['return_id'] != null) {
    return CaseDetailScreen(id: number(n['return_id']).toInt());
  }
  if (n['dispute_id'] != null) {
    return CaseDetailScreen(id: number(n['dispute_id']).toInt(), disputes: true);
  }
  return null;
}

String notificationTargetLabel(Map<String, dynamic> n) {
  if (n['order_id'] != null) return 'Voir la commande';
  if (n['product_id'] != null) return 'Voir le produit';
  if (n['return_id'] != null) return 'Voir le retour';
  if (n['dispute_id'] != null) return 'Voir le litige';
  return 'Voir le dossier';
}

/// « Aujourd'hui », « Hier » ou la date du jour de réception.
String notificationDay(Object? raw) {
  final date = DateTime.tryParse('$raw')?.toLocal();
  if (date == null) return 'Plus ancien';
  final now = DateTime.now();
  final diff = DateTime(now.year, now.month, now.day).difference(DateTime(date.year, date.month, date.day)).inDays;
  if (diff == 0) return 'Aujourd’hui';
  if (diff == 1) return 'Hier';
  const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  return '${date.day} ${months[date.month - 1]}${date.year == now.year ? '' : ' ${date.year}'}';
}

String notificationTime(Object? raw) {
  final date = DateTime.tryParse('$raw')?.toLocal();
  if (date == null) return '';
  return '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}

class _NotificationsState extends State<VendorNotificationsScreen> with LiveSyncListener<VendorNotificationsScreen> {
  List<Map<String, dynamic>> rows = [];
  bool loading = true, loaded = false;
  String? error;
  String query = '';
  int filter = 0;

  static const filters = ['Toutes', 'Non lues', 'Commandes', 'Finances', 'Litiges', 'Retours'];

  @override
  void initState() {
    super.initState();
    load();
    listenLiveSync({'notifications'}, () {
      if (!loading) load(silent: true);
    });
  }

  Future<void> load({bool silent = false}) async {
    if (!silent) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      rows = await allMenuRows('notifications');
      loaded = true;
      error = null;
    } catch (e) {
      if (!silent || !loaded) error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> readAll() async {
    try {
      await menuApi('notifications/read-all', method: 'POST');
      if (!mounted) return;
      setState(() {
        for (final n in rows) {
          n['read'] = true;
        }
      });
    } catch (e) {
      if (mounted) menuError(context, e);
    }
  }

  Future<void> open(Map<String, dynamic> n) async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => NotificationDetailScreen(id: '${n['id']}', initial: n)),
    );
    if (mounted) load(silent: true);
  }

  int _count(String category) => rows.where((n) => notificationCategory(n) == category).length;

  @override
  Widget build(BuildContext context) {
    final unread = rows.where((n) => n['read'] != true).length;
    final needle = query.trim().toLowerCase();
    final visible = rows
        .where(
          (n) =>
              (filter == 0 || (filter == 1 && n['read'] != true) || (filter > 1 && notificationCategory(n) == filters[filter])) &&
              '${n['title']} ${n['message']}'.toLowerCase().contains(needle),
        )
        .toList();
    final list = <Widget>[];
    String? currentDay;
    for (final n in visible) {
      final day = notificationDay(n['created_at']);
      if (day != currentDay) {
        currentDay = day;
        list.add(menuCaption(day));
      }
      list.add(_row(n));
    }

    return MenuPage(
      title: 'Notifications',
      subtitle: 'Consultez vos alertes, mises à jour et activités récentes',
      showLogo: true,
      refresh: load,
      loading: loading,
      error: error,
      hasContent: loaded,
      children: [
        MenuMetrics(
          items: [
            ('Toutes', '${rows.length}', Icons.notifications_none_rounded, menuAzure),
            ('Non lues', '$unread', Icons.mark_email_unread_outlined, menuOrange),
            ('Commandes', '${_count('Commandes')}', Icons.receipt_long_outlined, menuAzure),
            ('Système', '${_count('Système')}', Icons.settings_outlined, Mk.icon),
          ],
        ),
        menuSearch((v) => setState(() => query = v), 'Rechercher une notification…'),
        menuFilters(filters, filter, (v) => setState(() => filter = v)),
        Wrap(
          alignment: WrapAlignment.spaceBetween,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            menuCaption('Liste des notifications'),
            if (unread > 0)
              TextButton.icon(
                onPressed: readAll,
                icon: const Icon(Icons.done_all_rounded, size: 18),
                label: const Text('Tout marquer comme lu'),
                style: TextButton.styleFrom(
                  foregroundColor: orderBlue,
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                ),
              ),
          ],
        ),
        if (visible.isEmpty)
          menuEmpty(
            rows.isEmpty ? 'Aucune notification' : 'Aucune notification ne correspond',
            icon: Icons.notifications_off_outlined,
            hint: rows.isEmpty
                ? 'Vos alertes de commandes, de versements et de dossiers apparaîtront ici.'
                : 'Modifiez la recherche ou le filtre.',
          ),
        ...list,
      ],
    );
  }

  Widget _row(Map<String, dynamic> n) {
    final unread = n['read'] != true;
    final color = notificationColor(n);
    final time = notificationTime(n['created_at']);
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: unread ? const Color(0xFFF2F7FF) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => open(n),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                menuIcon(notificationIcon(n), color: color, size: 46),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        screenText(n['title'], 'Notification'),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: menuBlue,
                          fontSize: 14.5,
                          height: 1.25,
                          fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
                        ),
                      ),
                      if (screenText(n['message'], '').isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          '${n['message']}',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: menuMuted, fontSize: 13.5, height: 1.35, fontWeight: FontWeight.w500),
                        ),
                      ],
                      const SizedBox(height: 4),
                      Text(
                        [notificationDay(n['created_at']), if (time.isNotEmpty) time].join(' · '),
                        style: const TextStyle(color: Mk.hint, fontSize: 12, fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                ),
                Column(
                  children: [
                    Container(
                      width: 9,
                      height: 9,
                      margin: const EdgeInsets.fromLTRB(6, 6, 6, 8),
                      decoration: BoxDecoration(color: unread ? menuAzure : Colors.transparent, shape: BoxShape.circle),
                    ),
                    const Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Détail d'une notification : contenu, état de lecture et accès au dossier lié.
class NotificationDetailScreen extends StatefulWidget {
  const NotificationDetailScreen({super.key, required this.id, this.initial});
  final String id;
  final Map<String, dynamic>? initial;
  @override
  State<NotificationDetailScreen> createState() => _NotificationState();
}

class _NotificationState extends State<NotificationDetailScreen> {
  Map<String, dynamic> data = {};
  bool loading = true, marking = false;
  String? error;

  @override
  void initState() {
    super.initState();
    if (widget.initial != null) data = Map<String, dynamic>.from(widget.initial!);
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final detail = mapOf((await menuApi('notifications/${widget.id}'))['notification']);
      if (detail.isNotEmpty) data = detail;
    } catch (e) {
      error = ApiClient.friendlyError(e);
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> markRead({bool silent = false}) async {
    if (data['read'] == true) return;
    if (!silent) setState(() => marking = true);
    try {
      await menuApi('notifications/${widget.id}/read', method: 'POST');
      if (mounted) setState(() => data['read'] = true);
    } catch (e) {
      if (mounted && !silent) menuError(context, e);
    } finally {
      if (mounted && !silent) setState(() => marking = false);
    }
  }

  Future<void> openTarget(Widget target) async {
    // Ouvrir le dossier lié vaut lecture de la notification.
    markRead(silent: true);
    await Navigator.push(context, MaterialPageRoute<void>(builder: (_) => target));
  }

  @override
  Widget build(BuildContext context) {
    final target = data.isEmpty ? null : notificationTarget(data);
    final color = notificationColor(data);
    final read = data['read'] == true;
    final day = notificationDay(data['created_at']);
    final time = notificationTime(data['created_at']);
    final message = screenText(data['message'], '');
    return MenuPage(
      title: 'Détail de la notification',
      subtitle: 'Consultez le contenu complet de cette alerte',
      showLogo: true,
      refresh: load,
      loading: loading,
      error: error,
      hasContent: data.isNotEmpty,
      actions: [
        if (!read)
          MkBarButton(
            label: 'Marquer comme lue',
            icon: Icons.done_all_rounded,
            loading: marking,
            onTap: marking ? null : markRead,
          ),
        if (target != null)
          MkBarButton(
            label: notificationTargetLabel(data),
            icon: Icons.arrow_forward_rounded,
            filled: true,
            onTap: () => openTarget(target),
          ),
      ],
      children: [
        const SizedBox(height: 8),
        Center(child: menuIcon(notificationIcon(data), color: color, size: 78)),
        const SizedBox(height: 14),
        Text(
          screenText(data['title'], 'Notification'),
          textAlign: TextAlign.center,
          style: const TextStyle(color: menuBlue, fontSize: 20, height: 1.25, fontWeight: FontWeight.w800),
        ),
        if (message.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: menuMuted, fontSize: 14.5, height: 1.45, fontWeight: FontWeight.w500),
          ),
        ],
        const SizedBox(height: 16),
        DataCard(
          child: Column(
            children: [
              dataLine('Type', notificationCategory(data), icon: Icons.inventory_2_outlined),
              const Divider(height: 1, color: orderBorder),
              dataLine('Reçue', [day, if (time.isNotEmpty) 'à $time'].join(' '), icon: Icons.event_outlined),
              const Divider(height: 1, color: orderBorder),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    const Icon(Icons.mark_email_read_outlined, size: 19, color: Mk.icon),
                    const SizedBox(width: 10),
                    const Expanded(
                      flex: 5,
                      child: Text('Statut', style: TextStyle(color: menuMuted, fontSize: 13.5, fontWeight: FontWeight.w500)),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      flex: 6,
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: menuPill(read ? 'Lue' : 'Non lue', color: read ? menuGreen : menuAzure),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        DataCard(
          title: 'Historique',
          icon: Icons.schedule_rounded,
          child: Column(
            children: [
              _step('Notification générée', [day, if (time.isNotEmpty) 'à $time'].join(' '), done: true, last: false),
              _step(read ? 'Notification lue' : 'En attente de lecture', '', done: read, last: true),
            ],
          ),
        ),
        if (target != null)
          DataCard(
            title: 'Actions recommandées',
            icon: Icons.check_rounded,
            iconColor: menuGreen,
            child: Material(
              color: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: const BorderSide(color: orderBorder, width: 1.2),
              ),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => openTarget(target),
                child: const Padding(
                  padding: EdgeInsets.fromLTRB(12, 12, 8, 12),
                  child: Row(
                    children: [
                      Icon(Icons.description_outlined, color: Mk.icon, size: 22),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Consulter le dossier concerné',
                          style: TextStyle(color: menuBlue, fontSize: 14.5, fontWeight: FontWeight.w700),
                        ),
                      ),
                      Icon(Icons.chevron_right_rounded, color: Mk.icon, size: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _step(String label, String caption, {required bool done, required bool last}) => IntrinsicHeight(
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Column(
          children: [
            Container(
              width: 14,
              height: 14,
              margin: const EdgeInsets.only(top: 3),
              decoration: BoxDecoration(color: done ? menuAzure : const Color(0xFFC5CEDE), shape: BoxShape.circle),
            ),
            if (!last) Expanded(child: Container(width: 2, color: orderBorder)),
          ],
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Padding(
            padding: EdgeInsets.only(bottom: last ? 0 : 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(color: done ? menuBlue : menuMuted, fontSize: 14, fontWeight: done ? FontWeight.w700 : FontWeight.w500),
                ),
                if (caption.isNotEmpty)
                  Text(caption, style: const TextStyle(color: menuMuted, fontSize: 12.5, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}
