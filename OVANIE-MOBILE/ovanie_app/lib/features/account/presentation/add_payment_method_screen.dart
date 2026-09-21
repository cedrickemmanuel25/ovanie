import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../auth/domain/session_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

enum PaymentEntryType { mobileMoney, card }

class AddPaymentMethodScreen extends StatefulWidget {
  final PaymentEntryType initialType;
  final List<PaymentOperatorOption> operators;
  final bool makeDefault;

  const AddPaymentMethodScreen({
    super.key,
    this.initialType = PaymentEntryType.mobileMoney,
    this.operators = const <PaymentOperatorOption>[],
    this.makeDefault = false,
  });

  @override
  State<AddPaymentMethodScreen> createState() => _AddPaymentMethodScreenState();
}

class _AddPaymentMethodScreenState extends State<AddPaymentMethodScreen> {
  final ClientAccountRepository _repository = const ClientAccountRepository();
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  late PaymentEntryType _type;
  String _operator = 'wave';
  bool _isDefault = false;
  bool _saving = false;

  late final TextEditingController _holder;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _cardNumber;
  late final TextEditingController _expiry;
  late final TextEditingController _cvv;

  @override
  void initState() {
    super.initState();
    _type = widget.initialType;
    _isDefault = widget.makeDefault;
    final operators = _effectiveOperators;
    if (operators.isNotEmpty) _operator = operators.first.key;
    _holder = TextEditingController(text: SessionStore.instance.name ?? '');
    _phone = TextEditingController(text: SessionStore.instance.phone ?? '');
    _email = TextEditingController(text: SessionStore.instance.email ?? '');
    _cardNumber = TextEditingController();
    _expiry = TextEditingController();
    _cvv = TextEditingController();
  }

  List<PaymentOperatorOption> get _effectiveOperators {
    const preferredOrder = <String>['wave', 'orange', 'mtn', 'moov'];
    if (widget.operators.isNotEmpty) {
      final byKey = <String, PaymentOperatorOption>{
        for (final item in widget.operators) item.key: item,
      };
      return preferredOrder
          .where(byKey.containsKey)
          .map((key) => byKey[key]!)
          .toList(growable: false);
    }
    final remote =
        OvanieReferenceDataStore.instance.options('mobile_money_operators');
    if (remote.isNotEmpty) {
      final byKey = <String, PaymentOperatorOption>{
        for (final item in remote)
          item.code: PaymentOperatorOption(key: item.code, label: item.label),
      };
      return preferredOrder
          .where(byKey.containsKey)
          .map((key) => byKey[key]!)
          .toList(growable: false);
    }
    return const <PaymentOperatorOption>[];
  }

  @override
  void dispose() {
    _holder.dispose();
    _phone.dispose();
    _email.dispose();
    _cardNumber.dispose();
    _expiry.dispose();
    _cvv.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_type == PaymentEntryType.mobileMoney && _effectiveOperators.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Les opérateurs Mobile Money OVANIE sont indisponibles. Actualisez puis réessayez.',
          ),
        ),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      if (_type == PaymentEntryType.mobileMoney) {
        await _repository.addPaymentMethod(
          operator: _operator,
          accountName: _holder.text.trim(),
          phone: _phone.text.trim(),
          isDefault: _isDefault,
        );
      } else {
        final digits = _cardNumber.text.replaceAll(RegExp(r'\D'), '');
        final expiry = _parseExpiry(_expiry.text);
        await _repository.addCardPaymentMethod(
          accountName: _holder.text.trim(),
          brand: _cardBrand(digits),
          last4: digits.substring(digits.length - 4),
          expMonth: expiry[0],
          expYear: expiry[1],
          isDefault: _isDefault,
        );
      }
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(ApiClient.friendlyError(error))),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  List<int> _parseExpiry(String raw) {
    final parts = raw.split('/');
    final month = int.tryParse(parts.isNotEmpty ? parts.first : '') ?? 0;
    var year = int.tryParse(parts.length > 1 ? parts[1] : '') ?? 0;
    if (year < 100) year += 2000;
    return <int>[month, year];
  }

  String _cardBrand(String digits) {
    if (digits.startsWith('4')) return 'visa';
    return 'mastercard';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: CustomScrollView(
            physics: const BouncingScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
                  child: _AddHeader(onBack: () => Navigator.maybePop(context)),
                ),
              ),
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(56, 2, 18, 0),
                  child: Text(
                    'Enregistrez un moyen de paiement pour vos prochains achats.',
                    style: TextStyle(color: Color(0xFF40598E), fontSize: 12.7, height: 1.4),
                  ),
                ),
              ),
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.fromLTRB(18, 16, 18, 0),
                  child: _PrivacyBanner(),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(18, 20, 18, 0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const _SectionTitle('Type de moyen de paiement'),
                      const SizedBox(height: 10),
                      _PaymentTypeSelector(
                        value: _type,
                        onChanged: (value) => setState(() => _type = value),
                      ),
                      const SizedBox(height: 20),
                      if (_type == PaymentEntryType.mobileMoney)
                        _buildMobileMoney()
                      else
                        _buildCard(),
                      const SizedBox(height: 16),
                      _DefaultSwitch(
                        value: _isDefault,
                        onChanged: (value) => setState(() => _isDefault = value),
                      ),
                      const SizedBox(height: 16),
                      _SecureInfo(card: _type == PaymentEntryType.card),
                      const SizedBox(height: 18),
                      SizedBox(
                        width: double.infinity,
                        height: 54,
                        child: FilledButton(
                          onPressed: _saving ? null : _save,
                          style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFFFF3D0A),
                            disabledBackgroundColor: const Color(0xFFFFA48A),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          child: _saving
                              ? const SizedBox(
                                  width: 24,
                                  height: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                                )
                              : Text(
                                  _type == PaymentEntryType.card
                                      ? 'Enregistrer la carte'
                                      : 'Enregistrer le moyen de paiement',
                                  style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800),
                                ),
                        ),
                      ),
                      const SizedBox(height: 18),
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

  Widget _buildMobileMoney() {
    final operators = _effectiveOperators;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionTitle('Choisissez un opérateur'),
        const SizedBox(height: 10),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: operators.length,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: 1.9,
          ),
          itemBuilder: (context, index) {
            final option = operators[index];
            return _OperatorCard(
              option: option,
              selected: option.key == _operator,
              onTap: () => setState(() => _operator = option.key),
            );
          },
        ),
        const SizedBox(height: 20),
        const _SectionTitle('Informations du compte'),
        const SizedBox(height: 10),
        _LargeField(
          controller: _holder,
          label: 'Nom du titulaire',
          icon: Icons.person_outline_rounded,
          validator: _required,
        ),
        const SizedBox(height: 9),
        _LargeField(
          controller: _phone,
          label: 'Numéro de téléphone',
          icon: Icons.phone_outlined,
          keyboardType: TextInputType.phone,
          validator: (value) {
            final digits = (value ?? '').replaceAll(RegExp(r'\D'), '');
            return digits.length < 8 ? 'Renseignez un numéro de téléphone valide.' : null;
          },
        ),
        const SizedBox(height: 9),
        _LargeField(
          controller: _email,
          label: 'E-mail de réception (optionnel)',
          icon: Icons.mail_outline_rounded,
          keyboardType: TextInputType.emailAddress,
        ),
      ],
    );
  }

  Widget _buildCard() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _SectionTitle('Informations de la carte'),
        const SizedBox(height: 10),
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(11),
            border: Border.all(color: const Color(0xFFE0E5EF)),
            boxShadow: const [BoxShadow(color: Color(0x09071940), blurRadius: 12, offset: Offset(0, 4))],
          ),
          child: Column(
            children: [
              _LargeField(
                controller: _holder,
                label: 'Nom du titulaire',
                icon: Icons.person_outline_rounded,
                validator: _required,
                embedded: true,
              ),
              const SizedBox(height: 9),
              _CardNumberField(controller: _cardNumber),
              const SizedBox(height: 9),
              Row(
                children: [
                  Expanded(child: _ExpiryField(controller: _expiry)),
                  const SizedBox(width: 10),
                  Expanded(child: _CvvField(controller: _cvv)),
                ],
              ),
              const SizedBox(height: 9),
              _LargeField(
                controller: _email,
                label: 'Adresse e-mail de réception (optionnel)',
                icon: Icons.mail_outline_rounded,
                keyboardType: TextInputType.emailAddress,
                embedded: true,
              ),
            ],
          ),
        ),
      ],
    );
  }

  String? _required(String? value) {
    return (value ?? '').trim().isEmpty ? 'Ce champ est obligatoire.' : null;
  }
}

class _AddHeader extends StatelessWidget {
  final VoidCallback onBack;
  const _AddHeader({required this.onBack});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        IconButton(
          onPressed: onBack,
          icon: const Icon(Icons.arrow_back_rounded, size: 31, color: Color(0xFF071D59)),
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints.tightFor(width: 42, height: 42),
        ),
        const SizedBox(width: 8),
        const Expanded(
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              'Ajouter un moyen de paiement',
              maxLines: 1,
              style: TextStyle(
                color: Color(0xFF081D5A),
                fontSize: 24,
                height: 1.05,
                fontWeight: FontWeight.w800,
                letterSpacing: -.5,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _PrivacyBanner extends StatelessWidget {
  const _PrivacyBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFF),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: const Color(0xFFD1E0FA)),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            alignment: Alignment.center,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.shield_outlined, color: Color(0xFF0A63FF), size: 21),
          ),
          const SizedBox(width: 8),
          const Expanded(
            child: Text(
              'Vos informations sont protégées et utilisées uniquement\npour faciliter vos paiements.',
              style: TextStyle(
                color: Color(0xFF0C275F),
                fontSize: 12.7,
                height: 1.4,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String text;
  const _SectionTitle(this.text);

  @override
  Widget build(BuildContext context) => Text(
        text,
        style: const TextStyle(
          color: Color(0xFF071D58),
          fontSize: 17,
          fontWeight: FontWeight.w800,
        ),
      );
}

class _PaymentTypeSelector extends StatelessWidget {
  final PaymentEntryType value;
  final ValueChanged<PaymentEntryType> onChanged;
  const _PaymentTypeSelector({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _TypeCard(
            icon: Icons.phone_iphone_rounded,
            label: 'Mobile money',
            selected: value == PaymentEntryType.mobileMoney,
            onTap: () => onChanged(PaymentEntryType.mobileMoney),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _TypeCard(
            icon: Icons.credit_card_rounded,
            label: 'Carte bancaire',
            selected: value == PaymentEntryType.card,
            onTap: () => onChanged(PaymentEntryType.card),
          ),
        ),
      ],
    );
  }
}

class _TypeCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _TypeCard({required this.icon, required this.label, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(13),
      child: Container(
        height: 56,
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFFFF7F3) : Colors.white,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(
            color: selected ? const Color(0xFFFF4A21) : const Color(0xFFE0E4ED),
            width: selected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: selected ? const Color(0xFFFF3D0A) : const Color(0xFF071D58), size: 22),
            const SizedBox(width: 10),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                style: TextStyle(
                  color: selected ? const Color(0xFFFF3D0A) : const Color(0xFF071D58),
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            if (selected && valueIsCard(label)) ...[
              const SizedBox(width: 8),
              const CircleAvatar(
                radius: 12,
                backgroundColor: Color(0xFFFF3D0A),
                child: Icon(Icons.check_rounded, color: Colors.white, size: 15),
              ),
            ],
          ],
        ),
      ),
    );
  }

  bool valueIsCard(String text) => text == 'Carte bancaire';
}

class _OperatorCard extends StatelessWidget {
  final PaymentOperatorOption option;
  final bool selected;
  final VoidCallback onTap;
  const _OperatorCard({required this.option, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(11),
      child: Container(
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFFFFAF7) : Colors.white,
          borderRadius: BorderRadius.circular(11),
          border: Border.all(
            color: selected ? const Color(0xFFFF4A21) : const Color(0xFFE2E6EE),
            width: selected ? 1.6 : 1,
          ),
          boxShadow: const [BoxShadow(color: Color(0x09071940), blurRadius: 11, offset: Offset(0, 4))],
        ),
        child: Stack(
          children: [
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SizedBox(
                    width: 58,
                    height: 38,
                    child: Image.asset(_assetFor(option.key), fit: BoxFit.contain),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    option.label,
                    style: const TextStyle(
                      color: Color(0xFF0A205D),
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
            if (selected)
              const Positioned(
                right: 8,
                top: 8,
                child: CircleAvatar(
                  radius: 11,
                  backgroundColor: Color(0xFFFF3D0A),
                  child: Icon(Icons.check_rounded, color: Colors.white, size: 14),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _assetFor(String key) {
    switch (key) {
      case 'orange':
        return 'assets/images/operators/orange.png';
      case 'mtn':
        return 'assets/images/operators/mtn.png';
      case 'moov':
        return 'assets/images/operators/moov.png';
      default:
        return 'assets/images/operators/wave.png';
    }
  }
}

class _LargeField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final String? Function(String?)? validator;
  final bool embedded;

  const _LargeField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.validator,
    this.embedded = false,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      validator: validator,
      style: const TextStyle(color: Color(0xFF0B255F), fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: Color(0xFF476094), fontSize: 11.5),
        suffixIcon: Icon(icon, color: const Color(0xFF071D58), size: 21),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        filled: true,
        fillColor: Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xFFE0E5EF)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xFFE0E5EF)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(13),
          borderSide: const BorderSide(color: Color(0xFF1C59FF), width: 1.4),
        ),
      ),
    );
  }
}

class _CardNumberField extends StatelessWidget {
  final TextEditingController controller;
  const _CardNumberField({required this.controller});

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: TextInputType.number,
      inputFormatters: const [_CardNumberFormatter()],
      validator: (value) {
        final digits = (value ?? '').replaceAll(RegExp(r'\D'), '');
        if (digits.length < 13 || digits.length > 19) return 'Numéro de carte invalide.';
        return null;
      },
      style: const TextStyle(color: Color(0xFF0B255F), fontSize: 15, letterSpacing: .4),
      decoration: InputDecoration(
        labelText: 'Numéro de carte',
        labelStyle: const TextStyle(color: Color(0xFF476094), fontSize: 11.5),
        prefixIcon: const Icon(Icons.credit_card_rounded, color: Color(0xFF071D58)),
        suffixIcon: const Padding(
          padding: EdgeInsets.only(right: 16),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('VISA', style: TextStyle(color: Color(0xFF1345B8), fontSize: 16, fontWeight: FontWeight.w900, fontStyle: FontStyle.italic)),
              SizedBox(width: 10),
              _MastercardMini(),
            ],
          ),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFE0E5EF))),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFE0E5EF))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFF1C59FF), width: 1.4)),
      ),
    );
  }
}

class _ExpiryField extends StatelessWidget {
  final TextEditingController controller;
  const _ExpiryField({required this.controller});

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: TextInputType.number,
      inputFormatters: const [_ExpiryFormatter()],
      validator: (value) {
        final raw = (value ?? '').trim();
        final parts = raw.split('/');
        if (parts.length != 2) return 'Date invalide';
        final month = int.tryParse(parts[0]) ?? 0;
        var year = int.tryParse(parts[1]) ?? 0;
        if (year < 100) year += 2000;
        if (month < 1 || month > 12) return 'Mois invalide';
        final now = DateTime.now();
        if (year < now.year || (year == now.year && month < now.month)) return 'Carte expirée';
        return null;
      },
      style: const TextStyle(color: Color(0xFF0B255F), fontSize: 15),
      decoration: _compactDecoration('Date d’expiration', Icons.calendar_month_outlined),
    );
  }
}

class _CvvField extends StatelessWidget {
  final TextEditingController controller;
  const _CvvField({required this.controller});

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: TextInputType.number,
      obscureText: true,
      inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4)],
      validator: (value) {
        final len = (value ?? '').length;
        return len < 3 ? 'CVV invalide' : null;
      },
      style: const TextStyle(color: Color(0xFF0B255F), fontSize: 15),
      decoration: _compactDecoration('CVV', Icons.lock_outline_rounded).copyWith(
        suffixIcon: const Icon(Icons.info_outline_rounded, color: Color(0xFF071D58)),
      ),
    );
  }
}

InputDecoration _compactDecoration(String label, IconData icon) {
  return InputDecoration(
    labelText: label,
    labelStyle: const TextStyle(color: Color(0xFF476094), fontSize: 11.5),
    prefixIcon: Icon(icon, color: const Color(0xFF071D58)),
    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFE0E5EF))),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFFE0E5EF))),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(13), borderSide: const BorderSide(color: Color(0xFF1C59FF), width: 1.4)),
  );
}

class _MastercardMini extends StatelessWidget {
  const _MastercardMini();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 30,
      height: 20,
      child: Stack(
        children: [
          Positioned(left: 1, top: 2, child: _dot(const Color(0xFFE60019))),
          Positioned(right: 1, top: 2, child: _dot(const Color(0xFFFFA500))),
        ],
      ),
    );
  }

  Widget _dot(Color color) => Container(
        width: 18,
        height: 18,
        decoration: BoxDecoration(shape: BoxShape.circle, color: color.withValues(alpha: .94)),
      );
}

class _DefaultSwitch extends StatelessWidget {
  final bool value;
  final ValueChanged<bool> onChanged;
  const _DefaultSwitch({required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 56,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: const Color(0xFFE0E5EF)),
      ),
      child: Row(
        children: [
          const Expanded(
            child: Text(
              'Définir comme moyen de paiement par défaut',
              style: TextStyle(color: Color(0xFF0A235E), fontSize: 13.5, fontWeight: FontWeight.w500),
            ),
          ),
          Switch(
            value: value,
            onChanged: onChanged,
            activeColor: Colors.white,
            activeTrackColor: const Color(0xFFFF3D0A),
            inactiveTrackColor: const Color(0xFFD8DCE5),
          ),
        ],
      ),
    );
  }
}

class _SecureInfo extends StatelessWidget {
  final bool card;
  const _SecureInfo({required this.card});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF7F2),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: const Color(0xFFFFD7C9)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            alignment: Alignment.center,
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.lock_outline_rounded, color: Color(0xFFFF3D0A), size: 28),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Paiement sécurisé',
                  style: TextStyle(color: Color(0xFFFF3D0A), fontSize: 15, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Text(
                  card
                      ? 'Vos paiements sont sécurisés et chiffrés.\nOVANIE ne conserve jamais le numéro complet ni le CVV de votre carte.'
                      : 'Vous pourrez modifier ou supprimer ce moyen de paiement à tout moment.',
                  style: const TextStyle(color: Color(0xFF0A235E), fontSize: 12.2, height: 1.45),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CardNumberFormatter extends TextInputFormatter {
  const _CardNumberFormatter();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final limited = digits.length > 19 ? digits.substring(0, 19) : digits;
    final buffer = StringBuffer();
    for (var i = 0; i < limited.length; i++) {
      if (i > 0 && i % 4 == 0) buffer.write(' ');
      buffer.write(limited[i]);
    }
    final text = buffer.toString();
    return TextEditingValue(text: text, selection: TextSelection.collapsed(offset: text.length));
  }
}

class _ExpiryFormatter extends TextInputFormatter {
  const _ExpiryFormatter();

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final limited = digits.length > 4 ? digits.substring(0, 4) : digits;
    final text = limited.length <= 2 ? limited : '${limited.substring(0, 2)}/${limited.substring(2)}';
    return TextEditingValue(text: text, selection: TextSelection.collapsed(offset: text.length));
  }
}
