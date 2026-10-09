import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_top_bar.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import 'payout_settings_screen.dart';

/// Solde du vendeur : retrait vers son Mobile Money et compte de réception.
class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  Map<String, dynamic> _summary = {};
  Map<String, dynamic> _account = {};
  bool _loading = true;
  bool _withdrawing = false;
  String? _error;

  static const _operators = {
    'orange': 'Orange Money',
    'mtn': 'MTN Mobile Money',
    'wave': 'Wave',
    'moov': 'Moov Money',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        VendorRepository.instance.finance(),
        VendorRepository.instance.paymentSettings(),
      ]);
      if (!mounted) return;
      setState(() {
        _summary = orderMap(results[0]['summary']);
        _account = results[1];
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  double get _available => double.tryParse('${_summary['ready'] ?? 0}') ?? 0;
  double get _scheduled => double.tryParse('${_summary['scheduled'] ?? 0}') ?? 0;
  String get _operator => _operators['${_account['mm_operator'] ?? ''}'] ?? '';
  String get _number => '${_account['mm_number'] ?? ''}'.trim();
  String get _holder => '${_account['mm_holder'] ?? ''}'.trim();
  bool get _accountReady => _operator.isNotEmpty && _number.isNotEmpty && _holder.isNotEmpty;

  Future<void> _withdraw() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Retirer mon solde'),
        content: Text('${orderMoney(_available)} vers $_operator · $_number ($_holder).'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Retirer')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _withdrawing = true);
    try {
      final result = await VendorRepository.instance.withdrawBalance();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${result['message'] ?? 'Demande de retrait envoyée.'}')),
      );
      await _load();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(ApiClient.friendlyError(e))),
        );
      }
    } finally {
      if (mounted) setState(() => _withdrawing = false);
    }
  }

  Future<void> _editAccount() async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const PayoutSettingsScreen()),
    );
    if (saved == true && mounted) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FB),
      body: Column(
        children: [
          const VendorTopBar(title: 'Mon solde', showBack: true, showNotifications: false),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: orderOrange))
                : RefreshIndicator(
                    onRefresh: _load,
                    child: ListView(
                      padding: const EdgeInsets.all(16),
                      children: [
                        if (_error != null) ...[
                          Text(_error!, style: const TextStyle(color: Color(0xFFB42318))),
                          const SizedBox(height: 12),
                        ],
                        _balanceCard(),
                        const SizedBox(height: 16),
                        _sectionTitle('Compte de réception'),
                        const SizedBox(height: 8),
                        _accountCard(),
                        const SizedBox(height: 16),
                        _sectionTitle('Calendrier'),
                        const SizedBox(height: 8),
                        _infoRow(
                          Icons.event_repeat_rounded,
                          '${_account['payment_mode'] ?? ''}' == 'weekly'
                              ? 'Reversement chaque semaine'
                              : 'Reversement 72 h après la livraison',
                        ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _balanceCard() {
    final canWithdraw = _available > 0 && _accountReady && !_withdrawing;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF0B3C86),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Disponible au retrait', style: TextStyle(color: Color(0xFFCFDBF2), fontSize: 13)),
          const SizedBox(height: 4),
          Text(
            orderMoney(_available),
            style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w700),
          ),
          if (_scheduled > 0) ...[
            const SizedBox(height: 4),
            Text(
              '${orderMoney(_scheduled)} en attente de disponibilité',
              style: const TextStyle(color: Color(0xFFCFDBF2), fontSize: 13),
            ),
          ],
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: FilledButton(
              onPressed: canWithdraw ? _withdraw : null,
              style: FilledButton.styleFrom(
                backgroundColor: orderOrange,
                disabledBackgroundColor: Colors.white.withValues(alpha: .18),
                disabledForegroundColor: Colors.white70,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: _withdrawing
                  ? const SizedBox.square(
                      dimension: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : Text(
                      !_accountReady
                          ? 'Ajoutez votre compte pour retirer'
                          : _available > 0
                          ? 'Retirer vers $_operator'
                          : 'Aucun montant à retirer',
                      style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _accountCard() {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: _editAccount,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: orderBorder),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: const Color(0xFFEFF4FF),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.account_balance_wallet_outlined, color: orderBlue),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _accountReady
                    ? Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(_operator, style: const TextStyle(color: orderText, fontSize: 15, fontWeight: FontWeight.w600)),
                          Text('$_number · $_holder', style: const TextStyle(color: orderMuted, fontSize: 13)),
                        ],
                      )
                    : const Text(
                        'Ajouter un compte Mobile Money',
                        style: TextStyle(color: orderText, fontSize: 15, fontWeight: FontWeight.w600),
                      ),
              ),
              Text(_accountReady ? 'Modifier' : 'Ajouter', style: const TextStyle(color: orderBlue, fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _sectionTitle(String text) => Text(
        text,
        style: const TextStyle(color: orderText, fontSize: 15, fontWeight: FontWeight.w700),
      );

  Widget _infoRow(IconData icon, String text) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: orderBorder),
        ),
        child: Row(
          children: [
            Icon(icon, color: orderMuted),
            const SizedBox(width: 12),
            Expanded(child: Text(text, style: const TextStyle(color: orderText, fontSize: 14.5))),
          ],
        ),
      );
}
