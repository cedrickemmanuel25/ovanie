import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/returns_repository.dart';
import '../domain/return_model.dart';

class ReturnDetailScreen extends StatefulWidget {
  final ClientReturnCase initial;
  const ReturnDetailScreen({super.key, required this.initial});

  @override
  State<ReturnDetailScreen> createState() => _ReturnDetailScreenState();
}

/// État global affiché : en cours, refusé ou terminé (remboursé / clos).
enum _CaseState { ongoing, rejected, done }

class _ReturnDetailScreenState extends State<ReturnDetailScreen> {
  final ReturnsRepository _repository = const ReturnsRepository();
  late ClientReturnCase _item = widget.initial;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    // Les liens des photos sont temporaires : on recharge le dossier complet.
    _refresh();
  }

  Future<void> _refresh() async {
    if (_loading) return;
    setState(() => _loading = true);
    try {
      final result = await _repository.fetchCase(_item.id);
      if (!mounted) return;
      setState(() {
        _item = result;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _loading = false);
      showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    }
  }

  void _openSupport() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SupportCenterScreen(
          initialCategory: 'returns',
          contextType: 'return',
          contextId: _item.id,
          initialSubject: 'Assistance retour ${_item.orderNumber.isNotEmpty ? _item.orderNumber : '#${_item.id}'}',
        ),
      ),
    );
  }

  _CaseState get _state {
    if (_item.status == 'rejected' || _item.status == 'cancelled') return _CaseState.rejected;
    if (_item.status == 'refunded' ||
        _item.logisticsStatus == 'refunded' ||
        _item.status == 'resolved' ||
        _item.status == 'closed') {
      return _CaseState.done;
    }
    return _CaseState.ongoing;
  }

  String get _number =>
      'Retour #RET-${_item.createdAt?.year ?? DateTime.now().year}-${_item.id.toString().padLeft(5, '0')}';

  @override
  Widget build(BuildContext context) {
    final state = _state;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F8FA),
        bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
        body: SafeArea(
          bottom: false,
          child: Column(
            children: [
              SizedBox(
                height: 62,
                child: Row(
                  children: [
                    const SizedBox(width: 4),
                    IconButton(
                      onPressed: () => Navigator.of(context).maybePop(),
                      tooltip: 'Retour',
                      icon: const Icon(Icons.chevron_left_rounded, color: payNavy, size: 36),
                    ),
                    const Expanded(
                      child: Text(
                        'Détail du retour',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: payNavy, fontSize: 23, fontWeight: FontWeight.w800),
                      ),
                    ),
                    const SizedBox(width: 52),
                  ],
                ),
              ),
              Expanded(
                child: RefreshIndicator(
                  color: OvanieColors.orange,
                  onRefresh: _refresh,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(14, 4, 14, 20),
                    children: [
                      _Card(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: FittedBox(
                                    fit: BoxFit.scaleDown,
                                    alignment: Alignment.centerLeft,
                                    child: Text(
                                      _number,
                                      style: const TextStyle(color: payNavy, fontSize: 19, fontWeight: FontWeight.w800),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                _StatePill(state: state),
                              ],
                            ),
                            const SizedBox(height: 14),
                            Row(
                              children: [
                                OrderItemThumbnail(url: _item.imageUrl, width: 132, height: 92),
                                const SizedBox(width: 16),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        _item.productName,
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          color: payNavy,
                                          fontSize: 16.5,
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text('Commande #${_item.orderNumber}', style: _muted),
                                      if (_item.orderCreatedAt != null)
                                        Text('Acheté le ${paymentShortDate(_item.orderCreatedAt)}', style: _muted),
                                      Text('Qté retournée : ${_item.quantity}', style: _muted),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _Card(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Statut du retour', style: _title),
                            const SizedBox(height: 16),
                            _Steps(item: _item, state: state),
                            if (state != _CaseState.ongoing) ...[
                              const SizedBox(height: 14),
                              _StateBanner(state: state),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _Card(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Motif du retour', style: _title),
                            const SizedBox(height: 12),
                            _ReasonRow(item: _item),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _Card(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Informations de livraison du retour', style: _title),
                            const SizedBox(height: 8),
                            const _InfoRow(
                              icon: Icons.local_shipping_outlined,
                              label: 'Mode de retour',
                              value: 'Collecte à l’adresse indiquée par OVANIE',
                            ),
                            _InfoRow(
                              icon: Icons.location_on_outlined,
                              label: 'Adresse de collecte',
                              value: _item.pickupAddress.trim().isNotEmpty
                                  ? _item.pickupAddress
                                  : _item.collectionAddress,
                            ),
                            _InfoRow(
                              icon: Icons.calendar_month_outlined,
                              label: 'Date de collecte souhaitée',
                              value: _item.pickupDate == null ? '' : paymentShortDate(_item.pickupDate),
                            ),
                            _InfoRow(
                              icon: Icons.schedule_rounded,
                              label: 'Créneau préféré',
                              value: _item.pickupTimeSlot,
                            ),
                            _InfoRow(icon: Icons.call_outlined, label: 'Contact', value: _contactLine(_item)),
                            _InfoRow(
                              icon: Icons.info_outline_rounded,
                              label: 'Suivi',
                              value: _item.logisticsStatusLabel.trim().isNotEmpty
                                  ? _item.logisticsStatusLabel
                                  : 'OVANIE organisera la récupération et vous notifiera à chaque étape.',
                              accent: true,
                              last: true,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 12),
                      _Card(
                        child: _Summary(item: _item, state: state),
                      ),
                      if (state == _CaseState.ongoing) ...[
                        const SizedBox(height: 12),
                        const PaymentInfoNote(
                          background: Color(0xFFE8F1FC),
                          text:
                              'Votre retour est en cours de traitement par notre équipe. Vous serez notifié dès qu’une nouvelle étape sera confirmée.',
                        ),
                      ],
                      const SizedBox(height: 12),
                      _HelpCard(onTap: _openSupport),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

const _title = TextStyle(color: payNavy, fontSize: 19, fontWeight: FontWeight.w800);
const _muted = TextStyle(color: payMuted, fontSize: 14.5, height: 1.4);

String _contactLine(ClientReturnCase item) {
  final name = item.pickupContactName.trim().isNotEmpty ? item.pickupContactName.trim() : item.recipientName.trim();
  final phone = item.pickupContactPhone.trim().isNotEmpty ? item.pickupContactPhone : item.recipientPhone;
  return [
    name,
    if (phone.trim().isNotEmpty) formatCiPhoneDisplay(phone),
  ].where((part) => part.isNotEmpty).join('  •  ');
}

class _Card extends StatelessWidget {
  final Widget child;

  const _Card({required this.child});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: payBorder),
    ),
    child: child,
  );
}

class _StatePill extends StatelessWidget {
  final _CaseState state;

  const _StatePill({required this.state});

  @override
  Widget build(BuildContext context) {
    final (label, icon, color, background) = switch (state) {
      _CaseState.rejected => ('Refusé', Icons.close_rounded, const Color(0xFFDC2626), const Color(0xFFFFE8E8)),
      _CaseState.done => ('Terminé', Icons.check_rounded, payGreenDark, payGreenSoft),
      _CaseState.ongoing => ('En cours', Icons.schedule_rounded, const Color(0xFFC2410C), const Color(0xFFFFF1E6)),
    };
    return Container(
      padding: const EdgeInsets.fromLTRB(8, 7, 14, 7),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(10)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            child: Icon(icon, color: Colors.white, size: 16),
          ),
          const SizedBox(width: 8),
          Text(
            label,
            style: TextStyle(color: color, fontSize: 16, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

/// Quatre étapes du dossier, avec pastille Validé / En cours / Refusé.
class _Steps extends StatelessWidget {
  final ClientReturnCase item;
  final _CaseState state;

  const _Steps({required this.item, required this.state});

  @override
  Widget build(BuildContext context) {
    const labels = ['Demande initiée', 'Détails & preuves', 'Livraison du retour', 'Vérification & confirmation'];
    final collected = const {
      'return_in_transit',
      'return_received',
      'refund_pending',
      'refunded',
    }.contains(item.logisticsStatus);
    final proofs = item.photoCount > 0 || item.videoCount > 0 || item.detailedDescription.trim().isNotEmpty;

    // 'done', 'current', 'error' ou 'todo' pour chaque étape.
    final status = List<String>.filled(4, 'todo');
    status[0] = 'done';
    status[1] = proofs ? 'done' : 'current';
    switch (state) {
      case _CaseState.done:
        status.fillRange(0, 4, 'done');
      case _CaseState.rejected:
        if (proofs) status[2] = collected ? 'done' : 'todo';
        status[3] = 'error';
      case _CaseState.ongoing:
        if (proofs) {
          status[2] = collected ? 'done' : 'current';
          if (collected) status[3] = 'current';
        }
    }

    Widget dot(int i) {
      return switch (status[i]) {
        'done' => const CircleAvatar(
          radius: 21,
          backgroundColor: payGreenDark,
          child: Icon(Icons.check_rounded, color: Colors.white, size: 24),
        ),
        'current' => CircleAvatar(
          radius: 21,
          backgroundColor: OvanieColors.orange,
          child: Text(
            '${i + 1}',
            style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w700),
          ),
        ),
        'error' => const CircleAvatar(
          radius: 21,
          backgroundColor: Color(0xFFDC2626),
          child: Icon(Icons.close_rounded, color: Colors.white, size: 24),
        ),
        _ => CircleAvatar(
          radius: 21,
          backgroundColor: const Color(0xFFE6E9EE),
          child: Text(
            '${i + 1}',
            style: const TextStyle(color: payMuted, fontSize: 17, fontWeight: FontWeight.w700),
          ),
        ),
      };
    }

    Color lineColor(int i) => switch (status[i + 1]) {
      'done' => payGreenDark,
      'current' => OvanieColors.orange,
      'error' => const Color(0xFFDC2626),
      _ => const Color(0xFFDDE2EA),
    };

    Widget badge(int i) {
      final (label, color, background) = switch (status[i]) {
        'done' => ('Validé', payGreenDark, payGreenSoft),
        'current' => ('En cours', const Color(0xFFC2410C), const Color(0xFFFFF1E6)),
        'error' => ('Refusé', const Color(0xFFDC2626), const Color(0xFFFFE8E8)),
        _ => ('', payMuted, Colors.transparent),
      };
      if (label.isEmpty) return const SizedBox(height: 30);
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(16)),
        child: Text(
          label,
          style: TextStyle(color: color, fontSize: 13.5, fontWeight: FontWeight.w600),
        ),
      );
    }

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 26),
          child: Row(
            children: [
              for (var i = 0; i < 4; i++) ...[
                dot(i),
                if (i != 3) Expanded(child: Container(height: 3, color: lineColor(i))),
              ],
            ],
          ),
        ),
        const SizedBox(height: 8),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var i = 0; i < 4; i++)
              Expanded(
                child: Column(
                  children: [
                    Text(
                      labels[i].replaceFirst(' & conf', ' &\nconf'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: status[i] == 'current' ? OvanieColors.orange : payNavy,
                        fontSize: 12.5,
                        height: 1.25,
                        fontWeight: status[i] == 'current' ? FontWeight.w700 : FontWeight.w400,
                      ),
                    ),
                    const SizedBox(height: 6),
                    badge(i),
                  ],
                ),
              ),
          ],
        ),
      ],
    );
  }
}

class _StateBanner extends StatelessWidget {
  final _CaseState state;

  const _StateBanner({required this.state});

  @override
  Widget build(BuildContext context) {
    final rejected = state == _CaseState.rejected;
    final color = rejected ? const Color(0xFFDC2626) : payGreenDark;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
      decoration: BoxDecoration(
        color: rejected ? const Color(0xFFFFEEEE) : payGreenSoft,
        borderRadius: BorderRadius.circular(12),
        border: rejected ? Border.all(color: const Color(0xFFF7B7B7)) : null,
      ),
      child: Row(
        children: [
          rejected
              ? Icon(Icons.cancel_outlined, color: color, size: 30)
              : CircleAvatar(
                  radius: 15,
                  backgroundColor: color,
                  child: const Icon(Icons.check_rounded, color: Colors.white, size: 20),
                ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              rejected
                  ? 'Votre demande a été examinée et refusée. Consultez les informations du dossier ou contactez le support OVANIE pour toute précision.'
                  : 'Votre retour est finalisé et le remboursement associé a été traité.',
              style: TextStyle(color: rejected ? color : const Color(0xFF14532D), fontSize: 15, height: 1.4),
            ),
          ),
        ],
      ),
    );
  }
}

class _ReasonRow extends StatelessWidget {
  final ClientReturnCase item;

  const _ReasonRow({required this.item});

  @override
  Widget build(BuildContext context) {
    final photo = item.proofMedia.where((media) => !media.video).map((media) => media.url).firstOrNull;
    final description = item.detailedDescription.trim();
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (photo != null)
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Image.network(
              photo,
              width: 132,
              height: 92,
              fit: BoxFit.cover,
              cacheWidth: 360,
              errorBuilder: (_, __, ___) => const _ReasonIcon(),
            ),
          )
        else
          const _ReasonIcon(),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                item.reason.trim().isEmpty ? 'Retour produit' : item.reason.trim(),
                style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
              ),
              if (description.isNotEmpty) ...[const SizedBox(height: 4), Text(description, style: _muted)],
            ],
          ),
        ),
      ],
    );
  }
}

class _ReasonIcon extends StatelessWidget {
  const _ReasonIcon();

  @override
  Widget build(BuildContext context) => Container(
    width: 132,
    height: 92,
    decoration: BoxDecoration(color: const Color(0xFFFFF1E6), borderRadius: BorderRadius.circular(10)),
    child: const Icon(Icons.inventory_2_outlined, color: OvanieColors.orange, size: 40),
  );
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool accent;
  final bool last;

  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.accent = false,
    this.last = false,
  });

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 10),
    decoration: BoxDecoration(
      border: last ? null : const Border(bottom: BorderSide(color: payBorder)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: const BoxDecoration(color: Color(0xFFF1F3F6), shape: BoxShape.circle),
          child: Icon(icon, color: payNavy, size: 20),
        ),
        const SizedBox(width: 12),
        SizedBox(
          width: 108,
          child: Padding(
            padding: const EdgeInsets.only(top: 9),
            child: Text(label, style: const TextStyle(color: payMuted, fontSize: 13.5)),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 9),
            child: Text(
              value.trim().isEmpty ? '—' : value,
              style: TextStyle(color: accent ? payMuted : payNavy, fontSize: 14, height: 1.4),
            ),
          ),
        ),
      ],
    ),
  );
}

class _Summary extends StatelessWidget {
  final ClientReturnCase item;
  final _CaseState state;

  const _Summary({required this.item, required this.state});

  @override
  Widget build(BuildContext context) {
    final requested = (item.unitPrice ?? 0.0) * item.quantity;
    final refunded = item.refundAmount ?? requested;
    Widget line(String label, double amount) => Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(label, style: const TextStyle(color: payMuted, fontSize: 14.5)),
          ),
          Text(formatFcfa(amount), style: const TextStyle(color: payNavy, fontSize: 14.5)),
        ],
      ),
    );
    final (String firstLabel, double firstAmount, String totalLabel, double total) = switch (state) {
      _CaseState.done => ('Montant remboursé', refunded, 'Total remboursé', refunded),
      _CaseState.rejected => ('Montant demandé', requested, 'Total remboursé', 0.0),
      _CaseState.ongoing => ('Montant remboursable (estimé)', refunded, 'Total estimé du remboursement', refunded),
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Récapitulatif', style: _title),
        const SizedBox(height: 10),
        line(firstLabel, firstAmount),
        line('Frais de retour', 0),
        if (state == _CaseState.done && item.refundMethodLabel.trim().isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              children: [
                const Expanded(
                  child: Text('Méthode', style: TextStyle(color: payMuted, fontSize: 14.5)),
                ),
                Text(item.refundMethodLabel, style: const TextStyle(color: payNavy, fontSize: 14.5)),
              ],
            ),
          ),
        const Divider(height: 22, color: payBorder),
        Row(
          children: [
            Expanded(
              child: Text(
                totalLabel,
                style: const TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w800),
              ),
            ),
            Text(
              formatFcfa(total),
              style: const TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
            ),
          ],
        ),
      ],
    );
  }
}

class _HelpCard extends StatelessWidget {
  final VoidCallback onTap;

  const _HelpCard({required this.onTap});

  @override
  Widget build(BuildContext context) => _Card(
    child: Row(
      children: [
        Container(
          width: 64,
          height: 64,
          decoration: const BoxDecoration(color: Color(0xFFEFF3F9), shape: BoxShape.circle),
          child: const Icon(Icons.headset_mic_outlined, color: payNavy, size: 34),
        ),
        const SizedBox(width: 14),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Besoin d’aide ?',
                style: TextStyle(color: payNavy, fontSize: 16.5, fontWeight: FontWeight.w800),
              ),
              SizedBox(height: 3),
              Text(
                'Notre équipe est disponible pour vous accompagner.',
                style: TextStyle(color: payMuted, fontSize: 13),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        OutlinedButton(
          onPressed: onTap,
          style: OutlinedButton.styleFrom(
            foregroundColor: payNavy,
            side: const BorderSide(color: payNavy, width: 1.4),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          ),
          child: const Text('Nous contacter', style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w700)),
        ),
      ],
    ),
  );
}
