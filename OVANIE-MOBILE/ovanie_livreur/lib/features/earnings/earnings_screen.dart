import 'package:flutter/material.dart';

import '../../app/theme.dart';
import '../../core/network/api_client.dart';
import '../missions/widgets/mission_dialogs.dart';
import '../missions/widgets/mission_ui.dart';

/// « Mes gains » : chaque livraison est payée juste après la remise au client
/// par Mobile Money.
class EarningsScreen extends StatefulWidget {
  const EarningsScreen({super.key});

  @override
  State<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends State<EarningsScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final response = await ApiClient.dio.get<dynamic>('/driver/earnings');
      ApiClient.ensureSuccess(response);
      if (!mounted) return;
      setState(() {
        _data = Map<String, dynamic>.from(response.data as Map);
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _editAccount() async {
    final account = Map<String, dynamic>.from((_data?['account'] as Map?) ?? const {});
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _AccountSheet(
        operator: account['operator']?.toString(),
        phone: account['phone']?.toString(),
      ),
    );
    if (saved == true) {
      if (mounted) showMissionSnack(context, 'Compte Mobile Money enregistré.');
      await _load();
    }
  }

  double _num(Object? value) => value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

  @override
  Widget build(BuildContext context) {
    final summary = Map<String, dynamic>.from((_data?['summary'] as Map?) ?? const {});
    final account = Map<String, dynamic>.from((_data?['account'] as Map?) ?? const {});
    final payouts = ((_data?['payouts'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    final cashDue = _num(summary['cash_due']);
    final hasAccount = (account['phone']?.toString() ?? '').isNotEmpty;

    return Scaffold(
      backgroundColor: MissionPalette.mint,
      body: Column(
        children: [
          MissionPageHeader(
            title: 'Mes gains',
            subtitle: '',
            showBack: true,
            onBack: () => Navigator.of(context).pop(),
          ),
          Expanded(
            child: RefreshIndicator(
              color: OvanieColors.green,
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
                children: [
                  if (_loading && _data == null)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 80),
                      child: Center(child: CircularProgressIndicator(color: OvanieColors.green)),
                    )
                  else if (_error != null && _data == null)
                    MissionTintMessage(text: _error!, warning: true, icon: Icons.cloud_off_rounded)
                  else ...[
                    Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: OvanieColors.greenDark,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Ce mois-ci · ${summary['missions'] ?? 0} livraison${_num(summary['missions']) > 1 ? 's' : ''}',
                            style: const TextStyle(color: Color(0xFFCDEBDD), fontSize: 13),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            missionMoney(_num(summary['month'])),
                            style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              _HeroStat(label: 'Aujourd’hui', value: missionMoney(_num(summary['today']))),
                              _HeroStat(label: 'Déjà versé', value: missionMoney(_num(summary['paid_total']))),
                              _HeroStat(label: 'En cours', value: missionMoney(_num(summary['awaiting_transfer']))),
                            ],
                          ),
                        ],
                      ),
                    ),
                    if (cashDue > 0) ...[
                      const SizedBox(height: 12),
                      MissionTintMessage(
                        warning: summary['cash_blocked'] == true,
                        icon: Icons.payments_outlined,
                        text: summary['cash_blocked'] == true
                            ? 'Déposez ${missionMoney(cashDue)} d’espèces pour recevoir de nouvelles missions.'
                            : 'Espèces à déposer : ${missionMoney(cashDue)}',
                      ),
                    ],
                    const SizedBox(height: 18),
                    const Text('Compte de réception', style: TextStyle(color: MissionPalette.navy, fontSize: 15, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    Material(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      child: InkWell(
                        onTap: _editAccount,
                        borderRadius: BorderRadius.circular(14),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: hasAccount ? MissionPalette.cardBorder : const Color(0xFFF1D9A8)),
                          ),
                          child: Row(
                            children: [
                              const CircleAvatar(
                                radius: 20,
                                backgroundColor: Color(0xFFE9F6F0),
                                child: Icon(Icons.account_balance_wallet_outlined, color: OvanieColors.green, size: 20),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: hasAccount
                                    ? Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(_operatorLabel(account['operator']?.toString()), style: const TextStyle(color: MissionPalette.navy, fontSize: 15, fontWeight: FontWeight.w600)),
                                          Text(account['phone'].toString(), style: const TextStyle(color: MissionPalette.slate, fontSize: 13)),
                                        ],
                                      )
                                    : const Text('Ajouter mon Mobile Money', style: TextStyle(color: MissionPalette.navy, fontSize: 15, fontWeight: FontWeight.w600)),
                              ),
                              Text(hasAccount ? 'Modifier' : 'Ajouter', style: const TextStyle(color: OvanieColors.green, fontWeight: FontWeight.w700)),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text('Par livraison', style: TextStyle(color: MissionPalette.navy, fontSize: 15, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 8),
                    if (payouts.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 24),
                        child: Text(
                          'Aucun gain pour le moment.',
                          textAlign: TextAlign.center,
                          style: TextStyle(color: MissionPalette.slate, fontSize: 14),
                        ),
                      )
                    else
                      Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: MissionPalette.cardBorder),
                        ),
                        child: Column(
                          children: [
                            for (var i = 0; i < payouts.length; i++)
                              _PayoutRow(payout: payouts[i], amount: _num, last: i == payouts.length - 1),
                          ],
                        ),
                      ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

String _operatorLabel(String? operator) => switch (operator) {
      'wave' => 'Wave',
      'orange' => 'Orange Money',
      'mtn' => 'MTN MoMo',
      'moov' => 'Moov Money',
      _ => 'Mobile Money',
    };

class _HeroStat extends StatelessWidget {
  const _HeroStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(color: Color(0xFFCDEBDD), fontSize: 12)),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(value, style: const TextStyle(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      );
}

class _PayoutRow extends StatelessWidget {
  const _PayoutRow({required this.payout, required this.amount, required this.last});

  final Map<String, dynamic> payout;
  final double Function(Object?) amount;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final status = payout['status']?.toString() ?? '';
    final paid = status == 'paid' || status == 'kept_in_cash';
    final date = DateTime.tryParse(payout['created_at']?.toString() ?? '')?.toLocal();
    final cash = amount(payout['cash_collected']);
    final color = paid ? OvanieColors.green : const Color(0xFFB45309);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        border: last ? null : const Border(bottom: BorderSide(color: MissionPalette.cardBorder)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  payout['mission_number']?.toString() ?? '—',
                  style: const TextStyle(color: MissionPalette.navy, fontSize: 14, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 2),
                Text(
                  [
                    if (date != null) missionDate(date),
                    if (cash > 0) 'Espèces ${missionMoney(cash)}',
                  ].join(' · '),
                  style: const TextStyle(color: MissionPalette.slate, fontSize: 12.5),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                missionMoney(amount(payout['net_amount'])),
                style: const TextStyle(color: MissionPalette.navy, fontSize: 14.5, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                payout['status_label']?.toString() ?? (paid ? 'Versé' : 'En cours'),
                style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AccountSheet extends StatefulWidget {
  const _AccountSheet({this.operator, this.phone});

  final String? operator;
  final String? phone;

  @override
  State<_AccountSheet> createState() => _AccountSheetState();
}

class _AccountSheetState extends State<_AccountSheet> {
  late String? _operator = widget.operator;
  late final TextEditingController _phone = TextEditingController(text: widget.phone ?? '');
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final response = await ApiClient.dio.post<dynamic>(
        '/driver/earnings/account',
        data: {'operator': _operator, 'phone': _phone.text.trim()},
      );
      ApiClient.ensureSuccess(response);
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        padding: const EdgeInsets.fromLTRB(20, 18, 20, 24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SafeArea(
          top: false,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const MissionSectionTitle('Compte Mobile Money'),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final code in const ['wave', 'orange', 'mtn', 'moov'])
                    ChoiceChip(
                      label: Text(_operatorLabel(code)),
                      selected: _operator == code,
                      selectedColor: OvanieColors.green.withValues(alpha: .15),
                      onSelected: (_) => setState(() => _operator = code),
                    ),
                ],
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _phone,
                keyboardType: TextInputType.phone,
                onChanged: (_) => setState(() {}),
                decoration: const InputDecoration(
                  labelText: 'Numéro Mobile Money',
                  hintText: '07 00 00 00 00',
                  border: OutlineInputBorder(),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 10),
                Text(_error!, style: const TextStyle(color: MissionPalette.danger, fontSize: 12.5)),
              ],
              const SizedBox(height: 16),
              MissionPrimaryButton(
                label: 'Enregistrer',
                onPressed: _save,
                enabled: _operator != null && _phone.text.trim().isNotEmpty,
                loading: _saving,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
