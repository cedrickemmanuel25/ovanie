import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/mk_dialogs.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import '../orders/order_widgets.dart';
import '../shell/vendor_tabs.dart';
import 'finance_ui.dart';
import 'payout_detail_screen.dart';
import 'payout_settings_screen.dart';

/// Mon solde : montant disponible, retrait vers le Mobile Money du vendeur,
/// compte de versement et derniers versements.
class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  Map<String, dynamic> _summary = {};
  Map<String, dynamic> _account = {};
  List<Map<String, dynamic>> _payouts = [];
  bool _loading = true;
  bool _loaded = false;
  bool _withdrawing = false;
  String? _error;

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
        _payouts = orderList(results[0]['recent_payouts']).map(orderMap).toList();
        _account = results[1];
        _loaded = true;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  double _number(Object? raw) => double.tryParse('${raw ?? ''}') ?? 0;
  double get _available => _number(_summary['ready']);

  /// Part du solde dont le retrait est déjà demandé (virement en cours).
  double get _requested => _number(_summary['withdrawal_requested']);
  double get _withdrawable => (_available - _requested).clamp(0, double.infinity).toDouble();
  String get _operatorKey => '${_account['mm_operator'] ?? ''}';
  String get _operator => payoutOperators[_operatorKey] ?? '';
  String get _phone => payoutPhoneLabel(_account['mm_number']);
  String get _holder => '${_account['mm_holder'] ?? ''}'.trim();
  bool get _accountReady => _operator.isNotEmpty && _phone.isNotEmpty && _holder.isNotEmpty;

  Future<void> _settings() async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => const PayoutSettingsScreen()));
    if (saved == true && mounted) _load();
  }

  // ---- Retrait -------------------------------------------------------------------
  Future<void> _withdraw() async {
    final count = int.tryParse('${_summary['ready_count'] ?? 0}') ?? 0;
    final confirmed = await showMkSheet<bool>(
      context,
      builder: (sheet) => MkSheetFrame(
        children: [
          Image.asset('assets/images/mockups/withdraw.jpg', height: 130, excludeFromSemantics: true),
          const SizedBox(height: 10),
          const Text(
            'Retirer mon solde',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.navy, fontSize: 22, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          const Text(
            'Montant disponible au retrait',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.muted, fontSize: 14, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 2),
          Center(child: FinanceAmount(_withdrawable, size: 34)),
          if (count > 0)
            Text(
              count > 1 ? '$count versements validés' : '1 versement validé',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
            ),
          const SizedBox(height: 14),
          MkSheetSummary(child: _accountRow(size: 52)),
          const SizedBox(height: 10),
          const MkInfoBox(
            icon: Icons.info_rounded,
            child: Text('OVANIE effectue le virement sous 24 h sur ce compte Mobile Money.'),
          ),
          const SizedBox(height: 14),
          MkBarButton(label: 'Confirmer le retrait', filled: true, onTap: () => Navigator.pop(sheet, true)),
          TextButton(
            onPressed: () => Navigator.pop(sheet, false),
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
            child: const Text('Annuler', style: TextStyle(color: orderBlue, fontSize: 15, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final amount = _withdrawable;
    setState(() => _withdrawing = true);
    try {
      final result = await VendorRepository.instance.withdrawBalance();
      if (!mounted) return;
      await showMkDialog<void>(
        context,
        icon: Icons.check_rounded,
        iconColor: const Color(0xFF12894A),
        title: '${result['message'] ?? 'Demande de retrait envoyée. OVANIE effectue le virement sous 24 h.'}',
        extra: Center(child: FinanceAmount(amount, size: 34, unitColor: Mk.orange)),
        actions: const [MkDialogAction('Fermer', null)],
      );
      if (mounted) await _load();
    } catch (e) {
      if (mounted) mkToast(context, ApiClient.friendlyError(e), error: true);
    } finally {
      if (mounted) setState(() => _withdrawing = false);
    }
  }

  // ---- Explication du solde -------------------------------------------------------
  Future<void> _explain() async {
    final openSettings = await showBalanceInfoSheet(context, weekly: '${_account['payment_mode'] ?? ''}' == 'weekly');
    if (openSettings && mounted) _settings();
  }

  @override
  Widget build(BuildContext context) {
    return OrderPage(
      tab: VendorTab.finance,
      title: 'Mon solde',
      subtitle: 'Portefeuille et retrait',
      showLogo: true,
      loading: _loading,
      error: _error,
      hasContent: _loaded,
      emptyMessage: 'Votre solde est indisponible pour le moment.',
      onRefresh: _load,
      children: [
        _balanceCard(),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: _stat(Icons.schedule_rounded, Mk.orange, 'En attente', financePendingTotal(_summary))),
            const SizedBox(width: 8),
            Expanded(
              child: _stat(
                Icons.account_balance_wallet_rounded,
                financeGreen,
                'Reversements reçus',
                _summary['month_paid_amount'] ?? 0,
                note: 'ce mois',
              ),
            ),
          ],
        ),
        _accountCard(),
        const MkInfoBox(
          icon: Icons.info_rounded,
          child: Text('Les reversements sont effectués après validation des commandes livrées, selon la fréquence configurée.'),
        ),
        const Padding(padding: EdgeInsets.fromLTRB(4, 6, 4, 0), child: OrderCaption('Derniers versements')),
        _payoutsCard(),
      ],
    );
  }

  Widget _balanceCard() {
    final canWithdraw = _withdrawable >= 1 && _accountReady && !_withdrawing;
    final Widget action;
    if (!_accountReady) {
      action = _bannerButton('Ajouter mon compte de versement', Icons.add_circle_outline_rounded, onTap: _settings);
    } else if (_withdrawable >= 1) {
      action = _bannerButton(
        'Retirer mon solde',
        Icons.account_balance_wallet_outlined,
        onTap: canWithdraw ? _withdraw : null,
        loading: _withdrawing,
      );
    } else if (_requested >= 1) {
      action = _bannerButton('Retrait demandé · virement sous 24 h', Icons.schedule_rounded, state: 1);
    } else {
      action = _bannerButton('Aucun montant à retirer', Icons.schedule_rounded, state: 2);
    }
    return BalanceBanner(amount: _available, onInfo: _explain, bottom: action);
  }

  /// Bouton du bandeau : orange (action), clair (retrait en cours) ou gris (rien à retirer).
  Widget _bannerButton(String label, IconData icon, {VoidCallback? onTap, bool loading = false, int state = 0}) {
    final background = state == 0 ? null : (state == 1 ? const Color(0xFFEEF2FB) : const Color(0xFFDDE2EC));
    final foreground = state == 0 ? Colors.white : (state == 1 ? Mk.muted : const Color(0xFF8A95AE));
    return Semantics(
      button: state == 0,
      enabled: onTap != null,
      label: label,
      excludeSemantics: true,
      child: Material(
        color: background ?? Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: Ink(
          decoration: state == 0
              ? BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Color(0xFFFF8A1F), Color(0xFFF25C05)],
                  ),
                  borderRadius: BorderRadius.circular(12),
                )
              : null,
          child: InkWell(
            onTap: loading ? null : onTap,
            child: SizedBox(
              height: 50,
              child: Center(
                child: loading
                    ? const SizedBox.square(
                        dimension: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                      )
                    : FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(icon, color: state == 1 ? Mk.orange : foreground, size: 24),
                              const SizedBox(width: 10),
                              Text(
                                label,
                                style: TextStyle(color: foreground, fontSize: 15, fontWeight: FontWeight.w700),
                              ),
                            ],
                          ),
                        ),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _stat(IconData icon, Color color, String label, Object? amount, {String? note}) => OrderCard(
        padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
        child: Row(
          children: [
            FinanceIconDisc(icon, color: color, size: 44),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    maxLines: 2,
                    style: const TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.2, fontWeight: FontWeight.w500),
                  ),
                  FinanceAmount(amount, size: 18),
                  if (note != null) Text(note, style: const TextStyle(color: Mk.muted, fontSize: 12, fontWeight: FontWeight.w500)),
                ],
              ),
            ),
          ],
        ),
      );

  Widget _accountRow({double size = 56}) => Row(
        children: [
          PayoutOperatorLogo(operator: _operatorKey, size: size),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_operator, style: const TextStyle(color: Mk.navy, fontSize: 16.5, fontWeight: FontWeight.w800)),
                Text(_phone, style: const TextStyle(color: Mk.navy, fontSize: 15, fontWeight: FontWeight.w700)),
                Text(_holder, style: const TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
        ],
      );

  Widget _accountCard() => OrderCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.account_balance_wallet_outlined, color: Mk.navy, size: 24),
                const SizedBox(width: 10),
                const Expanded(child: OrderSectionTitle('Compte de versement')),
                TextButton(
                  onPressed: _settings,
                  style: TextButton.styleFrom(foregroundColor: orderBlue, padding: const EdgeInsets.symmetric(horizontal: 6)),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _accountReady ? 'Modifier' : 'Ajouter',
                        style: const TextStyle(fontFamily: Mk.font, fontSize: 14, fontWeight: FontWeight.w700),
                      ),
                      const Icon(Icons.chevron_right_rounded, size: 20),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            if (!_accountReady) ...[
              Center(
                child: Image.asset(
                  'assets/images/mockups/wallet_add_account.jpg',
                  height: 96,
                  excludeFromSemantics: true,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Ajoutez le compte Mobile Money qui recevra vos reversements.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Mk.muted, fontSize: 13.5, height: 1.4, fontWeight: FontWeight.w500),
              ),
              const SizedBox(height: 4),
            ] else
              Padding(
                padding: const EdgeInsets.fromLTRB(6, 6, 0, 6),
                child: IntrinsicHeight(
                  child: Row(
                    children: [
                      PayoutOperatorLogo(operator: _operatorKey, size: 62),
                      const SizedBox(width: 14),
                      const VerticalDivider(width: 1, thickness: 1, color: orderBorder),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Wrap(
                              spacing: 8,
                              runSpacing: 4,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                Text(
                                  _operator,
                                  style: const TextStyle(color: Mk.navy, fontSize: 16.5, fontWeight: FontWeight.w800),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(color: const Color(0xFFE4F5EA), borderRadius: BorderRadius.circular(99)),
                                  child: const Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(Icons.check_circle_rounded, color: financeGreen, size: 15),
                                      SizedBox(width: 4),
                                      Text(
                                        'Compte configuré',
                                        style: TextStyle(color: financeGreen, fontSize: 11.5, fontWeight: FontWeight.w600),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _holder,
                              style: const TextStyle(color: Mk.icon, fontSize: 14, fontWeight: FontWeight.w500),
                            ),
                            Text(
                              '+225 $_phone',
                              style: const TextStyle(color: Mk.icon, fontSize: 14, fontWeight: FontWeight.w500),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      );

  Widget _payoutsCard() {
    final rows = _payouts.take(3).toList();
    return OrderCard(
      padding: const EdgeInsets.fromLTRB(12, 6, 12, 6),
      child: rows.isEmpty
          ? Padding(
              padding: const EdgeInsets.symmetric(vertical: 22),
              child: Column(
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: const BoxDecoration(color: Color(0xFFEEF1F7), shape: BoxShape.circle),
                    child: const Icon(Icons.article_outlined, color: Mk.icon, size: 26),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Aucun versement pour le moment.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Mk.muted, fontSize: 13.5, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            )
          : Column(
              children: [
                for (var i = 0; i < rows.length; i++) ...[
                  if (i > 0) const Divider(height: 1, color: orderBorder),
                  InkWell(
                    onTap: () {
                      final id = int.tryParse('${rows[i]['id']}');
                      if (id == null) return;
                      Navigator.push(context, MaterialPageRoute<void>(builder: (_) => PayoutDetailScreen(payoutId: id)));
                    },
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      child: Row(
                        children: [
                          PayoutOperatorLogo(operator: payoutOperatorKey(rows[i]), size: 46),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                FittedBox(
                                  fit: BoxFit.scaleDown,
                                  alignment: Alignment.centerLeft,
                                  child: Text(
                                    '${rows[i]['reference'] ?? 'Versement'}',
                                    style: const TextStyle(color: Mk.navy, fontSize: 14, fontWeight: FontWeight.w800),
                                  ),
                                ),
                                Text(
                                  orderShortDate(rows[i]['paid_at'] ?? rows[i]['scheduled_for'] ?? rows[i]['created_at']),
                                  style: const TextStyle(color: Mk.muted, fontSize: 13, fontWeight: FontWeight.w500),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              ConstrainedBox(
                                constraints: const BoxConstraints(maxWidth: 130),
                                child: FinanceAmount(rows[i]['amount'], size: 15),
                              ),
                              const SizedBox(height: 4),
                              ConstrainedBox(
                                constraints: const BoxConstraints(maxWidth: 160),
                                child: PayoutStatusPill(status: rows[i]['status']),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ],
            ),
    );
  }
}

/// Feuille « Solde disponible » : ce que recouvrent le solde, l'attente et le
/// calendrier. Renvoie vrai si le vendeur veut ouvrir ses paramètres de reversement.
Future<bool> showBalanceInfoSheet(BuildContext context, {required bool weekly}) async {
    final openSettings = await showMkSheet<bool>(
      context,
      builder: (sheet) => MkSheetFrame(
        children: [
          Image.asset('assets/images/mockups/wallet_info.jpg', height: 140, excludeFromSemantics: true),
          const SizedBox(height: 10),
          const Text(
            'Solde disponible',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.navy, fontSize: 22, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          const Text(
            'Le solde disponible correspond aux reversements validés qui peuvent vous être versés.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Mk.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 12),
          _explainLine(
            Icons.account_balance_wallet_rounded,
            financeGreen,
            'Disponible au reversement',
            'Montant prêt à être envoyé sur votre compte Mobile Money.',
          ),
          const Divider(height: 1, indent: 58, color: orderBorder),
          _explainLine(Icons.schedule_rounded, Mk.orange, 'En attente', 'Ventes en attente de confirmation de livraison.'),
          const Divider(height: 1, indent: 58, color: orderBorder),
          _explainLine(
            Icons.calendar_month_rounded,
            const Color(0xFF1668E3),
            'Calendrier de reversement',
            weekly ? 'Paiement hebdomadaire, selon vos paramètres.' : 'Paiement 72 h après la livraison, selon vos paramètres.',
          ),
          const SizedBox(height: 10),
          const MkInfoBox(
            icon: Icons.info_rounded,
            child: Text('Les reversements sont effectués après validation des commandes livrées, selon la fréquence configurée.'),
          ),
          const SizedBox(height: 14),
          MkBarButton(label: 'J’ai compris', filled: true, onTap: () => Navigator.pop(sheet, false)),
          TextButton(
            onPressed: () => Navigator.pop(sheet, true),
            style: TextButton.styleFrom(minimumSize: const Size.fromHeight(44)),
            child: const Text(
              'Paramètres de reversement',
              style: TextStyle(color: orderBlue, fontSize: 15, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
    return openSettings == true;
}

Widget _explainLine(IconData icon, Color color, String title, String text) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            FinanceIconDisc(icon, color: color, size: 46),
            const SizedBox(width: 12),
            Expanded(
              child: Text.rich(
                TextSpan(
                  text: '$title — ',
                  style: const TextStyle(color: Mk.navy, fontWeight: FontWeight.w800),
                  children: [TextSpan(text: text, style: const TextStyle(color: Mk.muted, fontWeight: FontWeight.w500))],
                ),
                style: const TextStyle(fontSize: 14, height: 1.4),
              ),
            ),
          ],
        ),
      );

