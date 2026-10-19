import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../../core/utils/formatters.dart';
import '../../../core/widgets/in_app_payment_screen.dart';
import '../../auth/domain/session_store.dart';
import '../../checkout/data/checkout_repository.dart';
import '../../orders/domain/order_model.dart';
import 'payment_ui.dart';
import 'payment_waiting_screen.dart';

/// Paiement en ligne d'une commande déjà créée (« Payer maintenant »,
/// « Reprendre le paiement », « Réessayer »).
class ResumePaymentScreen extends StatefulWidget {
  final MobileOrder order;

  /// Opérateur présélectionné (ex. celui de la tentative précédente).
  final String initialOperator;

  const ResumePaymentScreen({super.key, required this.order, this.initialOperator = ''});

  @override
  State<ResumePaymentScreen> createState() => _ResumePaymentScreenState();
}

class _ResumePaymentScreenState extends State<ResumePaymentScreen> {
  static const _defaultOperators = ['wave', 'orange', 'mtn', 'moov', 'card'];

  final _checkout = const CheckoutRepository();
  final _phone = TextEditingController();
  final _otp = TextEditingController();
  late String _operator;
  bool _busy = false;
  bool _submitted = false;
  String? _message;

  /// Opérateurs publiés par le serveur ; à défaut, les moyens habituels
  /// (le serveur refuse de toute façon un opérateur indisponible).
  List<String> get _operators {
    final remote = OvanieReferenceDataStore.instance
        .options('checkout_operators')
        .map((item) => item.code)
        .where(_defaultOperators.contains)
        .toList(growable: false);
    return remote.isNotEmpty ? remote : _defaultOperators;
  }

  List<String> get _mobileOperators => _operators.where((code) => code != 'card').toList(growable: false);

  bool get _cardAvailable => _operators.contains('card');

  double get _amount => widget.order.outstandingAmount > 0 ? widget.order.outstandingAmount : widget.order.total;

  bool get _phoneValid => ciLocalPhoneDigits(_phone.text).length == 10;

  /// Une tentative a déjà eu lieu (échouée ou non confirmée) : on présente
  /// l'écran « Reprendre le paiement » avec le statut actuel.
  bool get _resumeMode =>
      widget.order.isPaymentFailed ||
      widget.order.isPaymentCancelled ||
      const {'pending', 'initiated', 'processing'}.contains(widget.order.paymentStatus);

  /// Même lecture que le détail de commande : une tentative échouée ou
  /// annulée s'affiche « Échoué », sinon le libellé fourni par OVANIE.
  String get _statusLabel {
    if (widget.order.isPaymentFailed || widget.order.isPaymentCancelled) return 'Échoué';
    final label = widget.order.paymentStatusLabel.trim();
    return label.isNotEmpty ? label : 'En attente';
  }

  void _checkStatus() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(
        builder: (_) => PaymentWaitingScreen(
          orderId: widget.order.id,
          orderNumber: widget.order.orderNumber,
          amount: _amount,
          methodLabel: operatorLabelOf(_operator),
          paymentUrl: '',
        ),
      ),
    );
  }

  @override
  void initState() {
    super.initState();
    final initial = widget.initialOperator.trim().toLowerCase();
    _operator = _operators.contains(initial)
        ? initial
        : (_mobileOperators.isNotEmpty ? _mobileOperators.first : 'card');
    _phone.text = formatCiPhoneDisplay(SessionStore.instance.phone ?? '', includeCountryCode: false);
  }

  @override
  void dispose() {
    _phone.dispose();
    _otp.dispose();
    super.dispose();
  }

  Future<void> _pay() async {
    if (_busy) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _submitted = true;
      _message = null;
    });
    if (!widget.order.actions.canResumePayment) {
      setState(() => _message = 'Le paiement en ligne n’est plus disponible pour cette commande.');
      return;
    }
    if (_operator != 'card' && !_phoneValid) return;

    setState(() => _busy = true);
    try {
      final result = await _checkout.startOnlinePayment(
        orderId: widget.order.id,
        operator: _operator,
        phone: _operator == 'card' ? '' : normalizeCiPhoneForApi(_phone.text),
        orangeOtp: _operator == 'orange' ? _otp.text.trim() : '',
      );
      if (!mounted) return;
      final url = result.paymentUrl.trim();
      if (_resumeMode) showOvanieToast(context, 'Paiement lancé. Nous vérifions sa confirmation.');
      if (url.isNotEmpty) {
        await InAppPaymentScreen.open(context, url);
        if (!mounted) return;
      }
      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => PaymentWaitingScreen(
            orderId: widget.order.id,
            orderNumber: widget.order.orderNumber,
            amount: _amount,
            methodLabel: operatorLabelOf(_operator),
            paymentUrl: url,
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _message = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _select(String code) {
    if (_busy) return;
    setState(() {
      _operator = code;
      _message = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_resumeMode) return _buildResume(context);
    final phoneError = _submitted && _operator != 'card' && !_phoneValid;
    final mobile = _mobileOperators;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: Column(
            children: [
              PaymentFlowHeader(
                title: 'Paiement en ligne',
                backLabel: 'Retour',
                onBack: () => Navigator.of(context).maybePop(),
              ),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 10, 20, 24),
                  children: [
                    Text(
                      _operator == 'card' ? 'Paiement par carte' : 'Paiement Mobile Money',
                      style: const TextStyle(color: payNavy, fontSize: 28, fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _operator == 'card'
                          ? 'Vous serez redirigé vers la page de paiement sécurisée.'
                          : 'Choisissez votre opérateur et saisissez le numéro à débiter.',
                      style: const TextStyle(color: payMuted, fontSize: 15.5, height: 1.4),
                    ),
                    const SizedBox(height: 20),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 18),
                      decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(14)),
                      child: Column(
                        children: [
                          PaymentInfoRow(label: 'Commande', value: widget.order.orderNumber, verticalPadding: 18),
                          PaymentInfoRow(
                            label: 'Montant',
                            last: true,
                            verticalPadding: 16,
                            valueWidget: FittedBox(
                              fit: BoxFit.scaleDown,
                              child: Text(
                                formatFcfa(_amount),
                                style: const TextStyle(color: payNavy, fontSize: 25, fontWeight: FontWeight.w900),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (mobile.isNotEmpty) ...[
                      const SizedBox(height: 24),
                      const Text(
                        'Opérateur',
                        style: TextStyle(color: payNavy, fontSize: 18, fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 12),
                      LayoutBuilder(
                        builder: (context, constraints) {
                          final width = (constraints.maxWidth - 12) / 2;
                          return Wrap(
                            spacing: 12,
                            runSpacing: 12,
                            children: [
                              for (final code in mobile)
                                SizedBox(
                                  width: width,
                                  child: _OperatorChoice(
                                    code: code,
                                    selected: _operator == code,
                                    onTap: () => _select(code),
                                  ),
                                ),
                            ],
                          );
                        },
                      ),
                    ],
                    if (_operator != 'card') ...[
                      const SizedBox(height: 24),
                      const Text(
                        'Numéro Mobile Money',
                        style: TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 10),
                      _PhoneField(controller: _phone, error: phoneError, onChanged: () => setState(() {})),
                      if (phoneError) ...[
                        const SizedBox(height: 6),
                        const Text(
                          'Saisissez le numéro Mobile Money ivoirien à 10 chiffres qui sera débité.',
                          style: TextStyle(color: payRed, fontSize: 12.5),
                        ),
                      ],
                    ],
                    if (_operator == 'orange') ...[
                      const SizedBox(height: 22),
                      const Text.rich(
                        TextSpan(
                          text: 'OTP Orange Money ',
                          style: TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w600),
                          children: [
                            TextSpan(
                              text: '(si demandé)',
                              style: TextStyle(color: payMuted, fontSize: 14, fontWeight: FontWeight.w400),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _otp,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(color: payNavy, fontSize: 16),
                        decoration: InputDecoration(
                          hintText: 'Code à usage unique',
                          hintStyle: const TextStyle(color: Color(0xFF8A93A8), fontSize: 16),
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: Color(0xFFD5DCE6)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                            borderSide: const BorderSide(color: OvanieColors.orange, width: 1.4),
                          ),
                        ),
                      ),
                    ],
                    if (_message != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFEEEE),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_rounded, color: payRed, size: 22),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(_message!, style: const TextStyle(color: payRed, fontSize: 13.5)),
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 24),
                    PaymentButton(label: 'Payer maintenant', loading: _busy, onPressed: _pay, height: 56),
                    const SizedBox(height: 14),
                    const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.lock_rounded, color: payMuted, size: 18),
                        SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            'Transaction sécurisée. Ne communiquez jamais votre code secret.',
                            style: TextStyle(color: payMuted, fontSize: 13),
                          ),
                        ),
                      ],
                    ),
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 18),
                      child: Divider(color: payBorder),
                    ),
                    Container(
                      padding: const EdgeInsets.fromLTRB(14, 16, 14, 14),
                      decoration: BoxDecoration(color: paySoft, borderRadius: BorderRadius.circular(14)),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Moyens acceptés',
                            style: TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              for (var i = 0; i < _operators.length; i++) ...[
                                Expanded(
                                  child: Container(
                                    height: 66,
                                    alignment: Alignment.center,
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Padding(
                                      padding: const EdgeInsets.all(6),
                                      child: FittedBox(child: PaymentOperatorLogo(code: _operators[i], size: 44)),
                                    ),
                                  ),
                                ),
                                if (i != _operators.length - 1) const SizedBox(width: 8),
                              ],
                            ],
                          ),
                          if (_cardAvailable) ...[
                            const SizedBox(height: 12),
                            InkWell(
                              onTap: () => _select(_operator == 'card' && mobile.isNotEmpty ? mobile.first : 'card'),
                              child: Text(
                                _operator == 'card' ? 'Payer par Mobile Money' : 'Payer par carte bancaire',
                                style: const TextStyle(
                                  color: payBlue,
                                  fontSize: 15,
                                  decoration: TextDecoration.underline,
                                  decorationColor: payBlue,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildResume(BuildContext context) {
    final phoneError = _submitted && _operator != 'card' && !_phoneValid;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.transparent,
          elevation: 0,
          titleSpacing: 0,
          leading: IconButton(
            onPressed: () => Navigator.of(context).maybePop(),
            tooltip: 'Retour',
            icon: const Icon(Icons.arrow_back_rounded, color: payNavy, size: 26),
          ),
          title: const Text(
            'Reprendre le paiement',
            style: TextStyle(color: payNavy, fontSize: 20, fontWeight: FontWeight.w800),
          ),
        ),
        bottomNavigationBar: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 6, 14, 6),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: FilledButton(
                    onPressed: _busy ? () {} : _pay,
                    style: FilledButton.styleFrom(
                      backgroundColor: OvanieColors.orange,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (_busy) ...[
                          const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                          ),
                          const SizedBox(width: 12),
                        ],
                        Flexible(
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            child: Text(
                              _busy ? 'Initialisation…' : 'Continuer vers le paiement sécurisé',
                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _busy ? null : _checkStatus,
                  child: const Text(
                    'Vérifier le statut maintenant',
                    style: TextStyle(
                      color: payBlue,
                      fontSize: 14.5,
                      decoration: TextDecoration.underline,
                      decorationColor: payBlue,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(14, 6, 14, 16),
          children: [
            Container(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
              decoration: BoxDecoration(color: const Color(0xFFFFF1E2), borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: const BoxDecoration(color: Color(0xFFA9440C), shape: BoxShape.circle),
                    child: const Icon(Icons.priority_high_rounded, color: Colors.white, size: 20),
                  ),
                  Container(
                    width: 1,
                    height: 40,
                    margin: const EdgeInsets.symmetric(horizontal: 14),
                    color: const Color(0xFFF2CDA8),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Statut actuel : $_statusLabel',
                          style: const TextStyle(color: Color(0xFF9A4A0C), fontSize: 13.5),
                        ),
                        const SizedBox(height: 2),
                        FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerLeft,
                          child: Text(
                            'Reste à payer : ${formatFcfa(_amount)}',
                            style: const TextStyle(color: Color(0xFF9A4A0C), fontSize: 18, fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Choisir le moyen de paiement',
              style: TextStyle(color: payNavy, fontSize: 17, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            for (final code in _operators) ...[
              _ResumeOperatorRow(code: code, selected: _operator == code, onTap: () => _select(code)),
              const SizedBox(height: 8),
            ],
            if (_operator != 'card') ...[
              const SizedBox(height: 8),
              const Text(
                'Numéro Mobile Money',
                style: TextStyle(color: payNavy, fontSize: 16, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              _PhoneField(controller: _phone, error: phoneError, onChanged: () => setState(() {})),
              if (phoneError) ...[
                const SizedBox(height: 6),
                const Text(
                  'Saisissez le numéro Mobile Money ivoirien à 10 chiffres qui sera débité.',
                  style: TextStyle(color: payRed, fontSize: 12.5),
                ),
              ],
            ],
            if (_operator == 'orange') ...[
              const SizedBox(height: 14),
              TextField(
                controller: _otp,
                keyboardType: TextInputType.number,
                style: const TextStyle(color: payNavy, fontSize: 16),
                decoration: InputDecoration(
                  labelText: 'OTP Orange Money (si demandé)',
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFD5DCE6)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: OvanieColors.orange, width: 1.4),
                  ),
                ),
              ),
            ],
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
              decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(12)),
              child: const Row(
                children: [
                  Icon(Icons.verified_user_rounded, color: Color(0xFF5E6B80), size: 26),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Le statut final de votre paiement est confirmé par OVANIE et son prestataire de paiement.',
                      style: TextStyle(color: Color(0xFF5E6B80), fontSize: 13, height: 1.4),
                    ),
                  ),
                ],
              ),
            ),
            if (_message != null) ...[
              const SizedBox(height: 12),
              Text(_message!, style: const TextStyle(color: payRed, fontSize: 13.5)),
            ],
          ],
        ),
      ),
    );
  }
}

class _ResumeOperatorRow extends StatelessWidget {
  const _ResumeOperatorRow({required this.code, required this.selected, required this.onTap});

  final String code;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? const Color(0xFFFFF4EC) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: selected ? OvanieColors.orange : payBorder, width: selected ? 1.4 : 1),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 14, 10),
          child: Row(
            children: [
              PaymentOperatorLogo(code: code, size: 46),
              const SizedBox(width: 16),
              Expanded(
                child: Text(
                  operatorLabelOf(code),
                  style: const TextStyle(color: payNavy, fontSize: 15.5, fontWeight: FontWeight.w700),
                ),
              ),
              Container(
                width: 22,
                height: 22,
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: selected ? OvanieColors.orange : const Color(0xFF9AA5B5), width: 1.6),
                ),
                child: selected
                    ? const DecoratedBox(
                        decoration: BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                      )
                    : null,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OperatorChoice extends StatelessWidget {
  final String code;
  final bool selected;
  final VoidCallback onTap;

  const _OperatorChoice({required this.code, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Container(
      height: 78,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: selected ? const Color(0xFFFFF6F0) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: selected ? OvanieColors.orange : payBorder, width: selected ? 1.6 : 1),
      ),
      child: Row(
        children: [
          PaymentOperatorLogo(code: code, size: 50),
          const SizedBox(width: 10),
          Expanded(
            child: FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                operatorLabelOf(code),
                maxLines: 1,
                style: const TextStyle(color: payNavy, fontSize: 15, fontWeight: FontWeight.w700),
              ),
            ),
          ),
          const SizedBox(width: 6),
          selected
              ? Container(
                  width: 28,
                  height: 28,
                  decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
                  child: const Icon(Icons.check_rounded, color: Colors.white, size: 19),
                )
              : Container(
                  width: 26,
                  height: 26,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFFB9C2D0), width: 1.5),
                  ),
                ),
        ],
      ),
    ),
  );
}

class _PhoneField extends StatelessWidget {
  final TextEditingController controller;
  final bool error;
  final VoidCallback onChanged;

  const _PhoneField({required this.controller, required this.error, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final border = error ? payRed : const Color(0xFFD5DCE6);
    return Container(
      height: 60,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: border, width: error ? 1.4 : 1),
      ),
      child: Row(
        children: [
          const SizedBox(width: 16),
          const Text('+225', style: TextStyle(color: payNavy, fontSize: 17)),
          const SizedBox(width: 6),
          const Icon(Icons.keyboard_arrow_down_rounded, color: payMuted, size: 22),
          const SizedBox(width: 10),
          Container(width: 1, height: 34, color: border),
          Expanded(
            child: TextField(
              controller: controller,
              keyboardType: TextInputType.phone,
              autofillHints: const [AutofillHints.telephoneNumber],
              inputFormatters: const [CiPhoneInputFormatter()],
              style: const TextStyle(color: payNavy, fontSize: 17),
              decoration: const InputDecoration(
                hintText: '07 00 00 00 00',
                hintStyle: TextStyle(color: Color(0xFF8A93A8)),
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                filled: false,
                contentPadding: EdgeInsets.symmetric(horizontal: 16),
              ),
              onChanged: (_) => onChanged(),
            ),
          ),
          if (error) ...[const Icon(Icons.error_rounded, color: payRed, size: 24), const SizedBox(width: 14)],
        ],
      ),
    );
  }
}
