import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);

class LoyaltyScreen extends StatefulWidget {
  const LoyaltyScreen({super.key});

  @override
  State<LoyaltyScreen> createState() => _LoyaltyScreenState();
}

class _LoyaltyScreenState extends State<LoyaltyScreen> {
  final _repository = const ClientAccountRepository();
  LoyaltyData? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await _repository.loyalty();
      if (!mounted) return;
      setState(() {
        _data = data;
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _points(int value) {
    final raw = value.abs().toString();
    final out = StringBuffer();
    for (var i = 0; i < raw.length; i++) {
      if (i > 0 && (raw.length - i) % 3 == 0) out.write(' ');
      out.write(raw[i]);
    }
    return '${value < 0 ? '-' : ''}$out';
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    return Scaffold(
      backgroundColor: Colors.white,
      bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: const Text(
          'Points & avantages OVANIE',
          style: TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
          : data == null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.cloud_off_rounded, color: _muted, size: 52),
                    const SizedBox(height: 14),
                    Text(
                      _error ?? 'Programme fidélité indisponible.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: _muted, height: 1.45),
                    ),
                    const SizedBox(height: 18),
                    PaymentButton(label: 'Réessayer', onPressed: _load),
                  ],
                ),
              ),
            )
          : RefreshIndicator(
              color: OvanieColors.orange,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 24),
                children: [
                  _BalanceCard(
                    points: _points(data.points),
                    value: formatFcfa(data.availableDiscountXof),
                    debt: _points(data.debt),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
                    decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(12)),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.info_outline_rounded, color: Color(0xFF4A586E), size: 22),
                        SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Vos points s’utilisent comme avantage fidélité lors de vos commandes.',
                            style: TextStyle(color: Color(0xFF4A586E), fontSize: 13.5, height: 1.4),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Padding(
                    padding: EdgeInsets.fromLTRB(2, 22, 2, 10),
                    child: Text(
                      'Historique',
                      style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
                    ),
                  ),
                  if (data.transactions.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 10),
                      child: Text(
                        'Aucun mouvement de points pour le moment.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: _muted, fontSize: 14.5),
                      ),
                    )
                  else
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: _line),
                      ),
                      child: Column(
                        children: [
                          for (var i = 0; i < data.transactions.length; i++) ...[
                            if (i > 0) const Divider(height: 1, color: _line),
                            _TransactionRow(tx: data.transactions[i], points: _points),
                          ],
                        ],
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.points, required this.value, required this.debt});

  final String points;
  final String value;
  final String debt;

  @override
  Widget build(BuildContext context) {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0A2552), Color(0xFF0D3A88), Color(0xFF0F63E0)],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -34,
            bottom: -40,
            child: Icon(Icons.star_rounded, size: 210, color: Colors.white.withValues(alpha: 0.08)),
          ),
          const Positioned(right: 14, top: 14, child: Icon(Icons.star_rounded, color: OvanieColors.orange, size: 46)),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 26, 20, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Solde fidélité', style: TextStyle(color: Colors.white, fontSize: 15.5)),
                const SizedBox(height: 4),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    '$points points',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 38,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -1,
                    ),
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Valeur disponible : $value',
                  style: const TextStyle(color: Color(0xFFFF9A3C), fontSize: 15, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 14),
                Text('Dette de points : $debt', style: const TextStyle(color: Colors.white, fontSize: 13.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TransactionRow extends StatelessWidget {
  const _TransactionRow({required this.tx, required this.points});

  final LoyaltyTransactionItem tx;
  final String Function(int) points;

  @override
  Widget build(BuildContext context) {
    final positive = tx.points >= 0;
    final label = tx.description.trim().isNotEmpty ? tx.description.trim() : tx.type;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Row(
        children: [
          SizedBox(
            width: 86,
            child: Text(paymentShortDate(tx.createdAt), style: const TextStyle(color: _muted, fontSize: 13)),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(label, style: const TextStyle(color: _navy, fontSize: 14, height: 1.3)),
          ),
          const SizedBox(width: 8),
          Text(
            '${positive ? '+' : '−'}${points(tx.points.abs())}',
            style: TextStyle(
              color: positive ? const Color(0xFF15803D) : const Color(0xFFD92D20),
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}
