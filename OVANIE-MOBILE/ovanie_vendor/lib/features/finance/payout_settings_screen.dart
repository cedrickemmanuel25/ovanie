import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import '../orders/order_ui.dart';
import '../orders/order_widgets.dart';
import '../shell/vendor_tabs.dart';
import 'data_ui.dart';
import 'finance_ui.dart';

/// Calendrier de reversement et compte Mobile Money du vendeur.
class PayoutSettingsScreen extends StatefulWidget {
  const PayoutSettingsScreen({super.key});

  @override
  State<PayoutSettingsScreen> createState() => _PayoutSettingsScreenState();
}

class _PayoutSettingsScreenState extends State<PayoutSettingsScreen> {
  final _form = GlobalKey<FormState>();
  final _phone = TextEditingController();
  final _holder = TextEditingController();
  String _mode = '';
  String _operator = '';
  bool _loading = true;
  bool _loaded = false;
  bool _saving = false;
  bool _submitted = false;
  String? _error;

  static const _modes = <String, (IconData, String, String)>{
    'post_delivery': (
      Icons.local_shipping_outlined,
      'Paiement après livraison (72 h)',
      'Vous êtes payé 72 heures après la livraison de la commande.',
    ),
    'weekly': (
      Icons.event_repeat_outlined,
      'Paiement hebdomadaire',
      'Vous êtes payé automatiquement chaque semaine.',
    ),
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _phone.dispose();
    _holder.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await VendorRepository.instance.paymentSettings();
      if (!mounted) return;
      setState(() {
        _mode = screenText(data['payment_mode'], '');
        _operator = screenText(data['mm_operator'], '');
        _phone.text = payoutPhoneLabel(data['mm_number']);
        _holder.text = screenText(data['mm_holder'], '');
        _loaded = true;
      });
    } catch (e) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    setState(() {
      _submitted = true;
      _error = null;
    });
    final fieldsValid = _form.currentState!.validate();
    if (!fieldsValid || !_modes.containsKey(_mode) || !payoutOperators.containsKey(_operator)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Complétez le calendrier et le compte de versement.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await VendorRepository.instance.savePaymentSettings({
        'payment_mode': _mode,
        'mm_operator': _operator,
        'mm_number': _phone.text.replaceAll(RegExp(r'\s'), ''),
        'mm_holder': _holder.text.trim(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Vos paramètres de reversement sont enregistrés.')),
      );
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      // Le bandeau d'erreur est en haut de page : le message est aussi affiché là où se trouve le bouton.
      final message = ApiClient.friendlyError(e);
      setState(() => _error = message);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickOperator() async {
    FocusScope.of(context).unfocus();
    final picked = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheet) => FinanceSheet(
        children: [
          const Text('Opérateur', style: TextStyle(color: Mk.navy, fontSize: 18, fontWeight: FontWeight.w800)),
          const SizedBox(height: 12),
          for (final entry in payoutOperators.entries) ...[
            FinanceRadioRow(
              label: entry.value,
              selected: _operator == entry.key,
              leading: PayoutOperatorLogo(operator: entry.key, size: 44),
              onTap: () => Navigator.pop(sheet, entry.key),
            ),
            const SizedBox(height: 8),
          ],
        ],
      ),
    );
    if (picked != null && mounted) setState(() => _operator = picked);
  }

  @override
  Widget build(BuildContext context) {
    return OrderPage(
      tab: VendorTab.finance,
      title: 'Paramètres de reversement',
      subtitle: 'Confirmez comment OVANIE doit vous reverser vos ventes',
      loading: _loading,
      error: _error,
      hasContent: _loaded,
      emptyMessage: 'Vos paramètres de reversement sont indisponibles.',
      onRefresh: _load,
      actions: [
        MkBarButton(
          label: 'Enregistrer mes paramètres',
          filled: true,
          loading: _saving,
          onTap: _saving ? null : _save,
        ),
      ],
      children: [
        _banner(),
        _scheduleCard(),
        Form(key: _form, child: _accountCard()),
      ],
    );
  }

  Widget _banner() {
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(color: const Color(0xFFD9EAFD), borderRadius: BorderRadius.circular(16)),
      child: LayoutBuilder(
        builder: (context, box) {
          final imageWidth = (box.maxWidth * .46).clamp(120.0, 230.0);
          return Stack(
            children: [
              Positioned(
                right: 0,
                top: 0,
                bottom: 0,
                width: imageWidth,
                child: ShaderMask(
                  blendMode: BlendMode.dstIn,
                  shaderCallback: (rect) => const LinearGradient(
                    colors: [Colors.transparent, Colors.white],
                    stops: [0, .16],
                  ).createShader(rect),
                  child: Image.asset(
                    'assets/images/mockups/fin_settings.jpg',
                    fit: BoxFit.cover,
                    alignment: Alignment.center,
                    excludeFromSemantics: true,
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(16, 18, imageWidth + 8, 18),
                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Vos ventes, directement sur votre compte',
                      style: TextStyle(color: Mk.navy, fontSize: 18, height: 1.2, fontWeight: FontWeight.w800, letterSpacing: -.2),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Des reversements sécurisés et automatiques pour développer votre activité en toute confiance.',
                      style: TextStyle(color: Mk.icon, fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _cardTitle(IconData icon, Color color, String title, String subtitle) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          FinanceIconDisc(icon, color: color, size: 42),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                OrderSectionTitle(title),
                const SizedBox(height: 2),
                Text(subtitle, style: const TextStyle(color: Mk.muted, fontSize: 13, height: 1.35, fontWeight: FontWeight.w500)),
              ],
            ),
          ),
        ],
      );

  Widget _scheduleCard() {
    final missing = _submitted && !_modes.containsKey(_mode);
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _cardTitle(
            Icons.calendar_today_outlined,
            Mk.orange,
            'Calendrier de reversement',
            'Choisissez quand vous souhaitez recevoir vos ventes.',
          ),
          const SizedBox(height: 12),
          for (final entry in _modes.entries) ...[
            _modeOption(entry.key, entry.value),
            const SizedBox(height: 8),
          ],
          if (missing)
            const Text(
              'Choisissez votre calendrier de reversement.',
              style: TextStyle(color: Mk.danger, fontSize: 12.5, fontWeight: FontWeight.w600),
            ),
        ],
      ),
    );
  }

  Widget _modeOption(String key, (IconData, String, String) mode) {
    final selected = _mode == key;
    return Semantics(
      inMutuallyExclusiveGroup: true,
      checked: selected,
      button: true,
      child: Material(
        color: selected ? Mk.softOrange : Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: selected ? Mk.orange : orderBorder, width: selected ? 1.5 : 1.2),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => setState(() => _mode = key),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
            child: Row(
              children: [
                FinanceIconDisc(mode.$1, color: selected ? Mk.orange : const Color(0xFF1668E3), size: 42),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(mode.$2, style: const TextStyle(color: Mk.navy, fontSize: 14.5, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 2),
                      Text(
                        mode.$3,
                        style: const TextStyle(color: Mk.muted, fontSize: 12.5, height: 1.35, fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 10),
                FinanceRadio(selected: selected),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _accountCard() {
    final operatorMissing = _submitted && !payoutOperators.containsKey(_operator);
    return OrderCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _cardTitle(
            Icons.account_balance_wallet_rounded,
            financeGreen,
            'Compte de versement',
            'Renseignez le compte Mobile Money sur lequel vous souhaitez recevoir vos reversements.',
          ),
          const SizedBox(height: 14),
          const MkLabel('Opérateur', required: true),
          const SizedBox(height: 6),
          Semantics(
            button: true,
            label: 'Opérateur : ${payoutOperators[_operator] ?? 'à choisir'}',
            child: Material(
              color: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
                side: BorderSide(color: operatorMissing ? Mk.danger : Mk.border, width: 1.2),
              ),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: _pickOperator,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(10, 7, 10, 7),
                  child: Row(
                    children: [
                      PayoutOperatorLogo(operator: _operator, size: 34),
                      const SizedBox(width: 10),
                      Expanded(
                        child: ExcludeSemantics(
                          child: Text(
                            payoutOperators[_operator] ?? 'Choisir un opérateur',
                            style: TextStyle(
                              color: payoutOperators.containsKey(_operator) ? Mk.navy : Mk.hint,
                              fontSize: 15,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ),
                      ),
                      const Icon(Icons.keyboard_arrow_down_rounded, color: Mk.icon, size: 24),
                    ],
                  ),
                ),
              ),
            ),
          ),
          if (operatorMissing)
            const Padding(
              padding: EdgeInsets.only(top: 6, left: 4),
              child: Text(
                'Choisissez l’opérateur de paiement.',
                style: TextStyle(color: Mk.danger, fontSize: 12.5, fontWeight: FontWeight.w600),
              ),
            ),
          const SizedBox(height: 14),
          const MkLabel('Numéro Mobile Money', required: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9+ ]')), LengthLimitingTextInputFormatter(20)],
            style: mkFieldText,
            decoration: mkInput(hint: '07 00 00 00 00', icon: Icons.call_outlined),
            validator: (v) {
              final digits = (v ?? '').replaceAll(RegExp(r'\D'), '');
              final local = digits.length == 13 && digits.startsWith('225') ? digits.substring(3) : digits;
              return local.length == 10 ? null : 'Le numéro Mobile Money doit contenir 10 chiffres.';
            },
          ),
          const SizedBox(height: 14),
          const MkLabel('Titulaire du compte', required: true),
          const SizedBox(height: 6),
          TextFormField(
            controller: _holder,
            textCapitalization: TextCapitalization.words,
            inputFormatters: [LengthLimitingTextInputFormatter(255)],
            style: mkFieldText,
            decoration: mkInput(hint: 'Nom et prénom', icon: Icons.person_outline_rounded),
            validator: (v) => (v ?? '').trim().length < 3 ? 'Indiquez le nom du titulaire du compte.' : null,
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: const Color(0xFFFFF4D6), borderRadius: BorderRadius.circular(12)),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                FinanceIconDisc(Icons.lock_rounded, color: Color(0xFFB36B00), size: 34),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Les anciennes valeurs ne sont utilisées pour un reversement qu’après votre confirmation. '
                    'Un reversement reste bloqué tant que cette configuration n’est pas complète.',
                    style: TextStyle(color: Color(0xFF7A4500), fontSize: 12.5, height: 1.4, fontWeight: FontWeight.w500),
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
