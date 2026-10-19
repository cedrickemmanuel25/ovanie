import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../core/reference_data/reference_data_store.dart';
import '../../../core/utils/formatters.dart';
import '../../auth/domain/session_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

enum PaymentEntryType { mobileMoney, card }

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _fieldBorder = Color(0xFFD5DCE6);

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

  late PaymentEntryType _type;
  String _operator = 'orange';
  bool _isDefault = false;
  bool _saving = false;
  String? _saveError;
  final Map<String, String> _errors = {};

  late final TextEditingController _holder;
  late final TextEditingController _phone;
  late final TextEditingController _cardNumber;
  late final TextEditingController _expiry;

  @override
  void initState() {
    super.initState();
    _type = widget.initialType;
    _isDefault = widget.makeDefault;
    final operators = _effectiveOperators;
    if (operators.isNotEmpty) _operator = operators.first.key;
    _holder = TextEditingController(text: SessionStore.instance.name ?? '');
    _phone = TextEditingController(
      text: formatCiPhoneDisplay(SessionStore.instance.phone ?? '', includeCountryCode: false),
    );
    _cardNumber = TextEditingController();
    _expiry = TextEditingController();
    for (final controller in [_holder, _cardNumber, _expiry]) {
      controller.addListener(() => setState(() {}));
    }
  }

  @override
  void dispose() {
    _holder.dispose();
    _phone.dispose();
    _cardNumber.dispose();
    _expiry.dispose();
    super.dispose();
  }

  List<PaymentOperatorOption> get _effectiveOperators {
    const preferredOrder = <String>['orange', 'mtn', 'moov', 'wave'];
    final byKey = <String, PaymentOperatorOption>{};
    if (widget.operators.isNotEmpty) {
      for (final item in widget.operators) {
        byKey[item.key] = item;
      }
    } else {
      for (final item in OvanieReferenceDataStore.instance.options('mobile_money_operators')) {
        byKey[item.code] = PaymentOperatorOption(key: item.code, label: item.label);
      }
    }
    return preferredOrder.where(byKey.containsKey).map((key) => byKey[key]!).toList(growable: false);
  }

  String get _cardDigits => _cardNumber.text.replaceAll(RegExp(r'\D'), '');

  String _cardBrand(String digits) => digits.startsWith('4') ? 'visa' : 'mastercard';

  bool _luhn(String digits) {
    var sum = 0;
    var alternate = false;
    for (var i = digits.length - 1; i >= 0; i--) {
      var n = int.parse(digits[i]);
      if (alternate) {
        n *= 2;
        if (n > 9) n -= 9;
      }
      sum += n;
      alternate = !alternate;
    }
    return sum % 10 == 0;
  }

  /// [mois, année sur 4 chiffres] ou null si la saisie est incomplète.
  List<int>? _parseExpiry(String raw) {
    final parts = raw.split('/');
    if (parts.length != 2) return null;
    final month = int.tryParse(parts[0]);
    var year = int.tryParse(parts[1]);
    if (month == null || year == null || month < 1 || month > 12) return null;
    if (year < 100) year += 2000;
    return [month, year];
  }

  bool _validate() {
    final errors = <String, String>{};
    if (_holder.text.trim().isEmpty) errors['holder'] = 'Renseignez le nom du titulaire.';
    if (_type == PaymentEntryType.mobileMoney) {
      if (ciLocalPhoneDigits(_phone.text).length != 10) errors['phone'] = 'Numéro de téléphone invalide.';
    } else {
      final digits = _cardDigits;
      if (digits.length < 13 || digits.length > 19 || !_luhn(digits)) errors['card'] = 'Numéro de carte invalide.';
      final expiry = _parseExpiry(_expiry.text);
      if (expiry == null) {
        errors['expiry'] = 'Date invalide';
      } else {
        final now = DateTime.now();
        if (expiry[1] < now.year || (expiry[1] == now.year && expiry[0] < now.month)) {
          errors['expiry'] = 'Carte expirée';
        }
      }
    }
    setState(() {
      _errors
        ..clear()
        ..addAll(errors);
    });
    return errors.isEmpty;
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    setState(() => _saveError = null);
    if (!_validate()) return;
    if (_type == PaymentEntryType.mobileMoney && _effectiveOperators.isEmpty) {
      setState(() => _saveError = 'Les opérateurs Mobile Money OVANIE sont indisponibles. Actualisez puis réessayez.');
      return;
    }
    setState(() => _saving = true);
    try {
      if (_type == PaymentEntryType.mobileMoney) {
        await _repository.addPaymentMethod(
          operator: _operator,
          accountName: _holder.text.trim(),
          phone: normalizeCiPhoneForApi(_phone.text),
          isDefault: _isDefault,
        );
      } else {
        final digits = _cardDigits;
        final expiry = _parseExpiry(_expiry.text)!;
        // Seuls la marque, les 4 derniers chiffres et l'échéance quittent le téléphone.
        await _repository.addCardPaymentMethod(
          accountName: _holder.text.trim(),
          brand: _cardBrand(digits),
          last4: digits.substring(digits.length - 4),
          expMonth: expiry[0],
          expYear: expiry[1],
          isDefault: _isDefault,
        );
      }
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) setState(() => _saveError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _clearError(String key) {
    if (_errors.containsKey(key)) setState(() => _errors.remove(key));
  }

  @override
  Widget build(BuildContext context) {
    final card = _type == PaymentEntryType.card;
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        toolbarHeight: 80,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: const Column(
          children: [
            Text(
              'Ajouter un moyen de paiement',
              style: TextStyle(color: _navy, fontSize: 18.5, fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 3),
            Text(
              'Enregistrez un moyen de paiement pour vos prochains achats.',
              textAlign: TextAlign.center,
              softWrap: true,
              maxLines: 2,
              style: TextStyle(color: _muted, fontSize: 12),
            ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          decoration: const BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: _line)),
          ),
          child: SizedBox(
            height: 50,
            child: FilledButton(
              onPressed: _saving ? () {} : _save,
              style: FilledButton.styleFrom(
                backgroundColor: OvanieColors.orange,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              child: _saving
                  ? const SizedBox.square(
                      dimension: 22,
                      child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white),
                    )
                  : Text(
                      card ? 'Enregistrer la carte' : 'Enregistrer le moyen de paiement',
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                    ),
            ),
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(14, 8, 14, 20),
        children: [
          const _Title('Type de moyen de paiement'),
          Row(
            children: [
              Expanded(
                child: _TypeTile(
                  icon: Icons.phone_iphone_rounded,
                  label: 'Mobile money',
                  selected: !card,
                  onTap: () => setState(() {
                    _type = PaymentEntryType.mobileMoney;
                    _errors.clear();
                  }),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _TypeTile(
                  icon: Icons.credit_card_rounded,
                  label: 'Carte bancaire',
                  selected: card,
                  onTap: () => setState(() {
                    _type = PaymentEntryType.card;
                    _errors.clear();
                  }),
                ),
              ),
            ],
          ),
          if (card) ..._cardSection() else ..._mobileSection(),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.fromLTRB(14, 4, 6, 4),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _line),
            ),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Définir comme moyen de paiement par défaut',
                    style: TextStyle(color: _navy, fontSize: 14.5),
                  ),
                ),
                Switch(
                  value: _isDefault,
                  onChanged: (value) => setState(() => _isDefault = value),
                  activeThumbColor: Colors.white,
                  activeTrackColor: OvanieColors.orange,
                  inactiveThumbColor: Colors.white,
                  inactiveTrackColor: const Color(0xFFDDE2E9),
                  trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
            decoration: BoxDecoration(color: const Color(0xFFEDF7F0), borderRadius: BorderRadius.circular(12)),
            child: const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.lock_rounded, color: Color(0xFF13804A), size: 26),
                SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Paiement sécurisé',
                        style: TextStyle(color: Color(0xFF13804A), fontSize: 15, fontWeight: FontWeight.w700),
                      ),
                      SizedBox(height: 3),
                      Text.rich(
                        TextSpan(
                          text: 'Vos paiements sont sécurisés et chiffrés. ',
                          children: [
                            TextSpan(
                              text: 'OVANIE',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                            TextSpan(text: ' ne conserve jamais le numéro complet de votre carte.'),
                          ],
                        ),
                        style: TextStyle(color: Color(0xFF4A586E), fontSize: 13, height: 1.4),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'Vous pourrez modifier ou supprimer ce moyen de paiement à tout moment.',
            style: TextStyle(color: _muted, fontSize: 13, height: 1.35),
          ),
          if (_saveError != null) ...[
            const SizedBox(height: 12),
            Text(_saveError!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5)),
          ],
        ],
      ),
    );
  }

  List<Widget> _mobileSection() {
    final operators = _effectiveOperators;
    return [
      const _Title('Choisissez un opérateur'),
      Row(
        children: [
          for (var i = 0; i < operators.length; i++) ...[
            if (i > 0) const SizedBox(width: 8),
            Expanded(
              child: _OperatorTile(
                option: operators[i],
                selected: operators[i].key == _operator,
                onTap: () => setState(() => _operator = operators[i].key),
              ),
            ),
          ],
        ],
      ),
      const _Title('Informations du compte'),
      Container(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _Input(
              label: 'Nom du titulaire',
              controller: _holder,
              error: _errors['holder'],
              onChanged: () => _clearError('holder'),
              capitalization: TextCapitalization.words,
            ),
            _Input(
              label: 'Numéro de téléphone',
              controller: _phone,
              prefix: '+225',
              hint: '07 08 12 34 56',
              keyboardType: TextInputType.phone,
              formatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9 ]')), LengthLimitingTextInputFormatter(14)],
              error: _errors['phone'],
              onChanged: () => _clearError('phone'),
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _cardSection() {
    final digits = _cardDigits;
    return [
      const SizedBox(height: 14),
      _CardPreview(
        brand: digits.isEmpty ? '' : _cardBrand(digits),
        digits: digits,
        holder: _holder.text.trim(),
        expiry: _expiry.text.trim(),
      ),
      const _Title('Informations de la carte'),
      _Input(
        label: 'Numéro de carte',
        controller: _cardNumber,
        hint: '1234 5678 9012 3456',
        keyboardType: TextInputType.number,
        formatters: [
          FilteringTextInputFormatter.digitsOnly,
          LengthLimitingTextInputFormatter(19),
          _CardNumberFormatter(),
        ],
        suffix: const Icon(Icons.credit_card_rounded, color: Color(0xFF6B7890)),
        error: _errors['card'],
        onChanged: () => _clearError('card'),
        compactTop: true,
      ),
      _Input(
        label: 'Nom du titulaire',
        controller: _holder,
        capitalization: TextCapitalization.characters,
        error: _errors['holder'],
        onChanged: () => _clearError('holder'),
      ),
      _Input(
        label: 'Date d’expiration',
        controller: _expiry,
        hint: 'MM/AA',
        keyboardType: TextInputType.number,
        formatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(4), _ExpiryFormatter()],
        suffix: const Icon(Icons.calendar_today_outlined, color: Color(0xFF6B7890), size: 20),
        error: _errors['expiry'],
        onChanged: () => _clearError('expiry'),
      ),
    ];
  }
}

class _Title extends StatelessWidget {
  const _Title(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 18, bottom: 10),
    child: Text(
      text,
      style: const TextStyle(color: _navy, fontSize: 16.5, fontWeight: FontWeight.w700),
    ),
  );
}

class _TypeTile extends StatelessWidget {
  const _TypeTile({required this.icon, required this.label, required this.selected, required this.onTap});

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? const Color(0xFFFFF4EC) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(color: selected ? OvanieColors.orange : _fieldBorder, width: selected ? 1.4 : 1),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(10, 12, 8, 12),
          child: Row(
            children: [
              Container(
                width: 20,
                height: 20,
                padding: const EdgeInsets.all(3.5),
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
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(icon, color: _navy, size: 24),
                    const SizedBox(height: 4),
                    FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(
                        label,
                        style: const TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w500),
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
}

String? _operatorAsset(String key) => switch (key) {
  'wave' => 'assets/images/operators/wave.png',
  'orange' => 'assets/images/operators/orange.png',
  'mtn' => 'assets/images/operators/mtn.png',
  'moov' => 'assets/images/operators/moov.png',
  _ => null,
};

class _OperatorTile extends StatelessWidget {
  const _OperatorTile({required this.option, required this.selected, required this.onTap});

  final PaymentOperatorOption option;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final asset = _operatorAsset(option.key);
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Material(
          color: selected ? const Color(0xFFFFF4EC) : Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
            side: BorderSide(color: selected ? OvanieColors.orange : _fieldBorder, width: selected ? 1.4 : 1),
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(10),
            child: SizedBox(
              height: 100,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(6, 12, 6, 8),
                child: Column(
                  children: [
                    Expanded(
                      child: asset == null
                          ? const Icon(Icons.account_balance_wallet_rounded, color: _navy, size: 34)
                          : ClipRRect(
                              borderRadius: BorderRadius.circular(8),
                              child: Image.asset(asset, fit: BoxFit.contain),
                            ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      option.label,
                      maxLines: 2,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: _navy, fontSize: 12, fontWeight: FontWeight.w600, height: 1.15),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
        if (selected)
          Positioned(
            right: 5,
            top: 5,
            child: Container(
              width: 18,
              height: 18,
              decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
              child: const Icon(Icons.check_rounded, color: Colors.white, size: 13),
            ),
          ),
      ],
    );
  }
}

class _Input extends StatelessWidget {
  const _Input({
    required this.label,
    required this.controller,
    this.hint,
    this.prefix,
    this.suffix,
    this.error,
    this.onChanged,
    this.keyboardType,
    this.formatters,
    this.capitalization = TextCapitalization.none,
    this.compactTop = false,
  });

  final String label;
  final TextEditingController controller;
  final String? hint;
  final String? prefix;
  final Widget? suffix;
  final String? error;
  final VoidCallback? onChanged;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? formatters;
  final TextCapitalization capitalization;
  final bool compactTop;

  @override
  Widget build(BuildContext context) {
    final hasError = error != null;
    final borderColor = hasError ? OvanieColors.danger : _fieldBorder;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: EdgeInsets.only(top: compactTop ? 0 : 14, bottom: 6),
          child: Text(label, style: const TextStyle(color: Color(0xFF3B4A60), fontSize: 13.5)),
        ),
        Container(
          height: 48,
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(9),
            border: Border.all(color: borderColor, width: 1.1),
          ),
          child: Row(
            children: [
              if (prefix != null)
                Container(
                  width: 62,
                  alignment: Alignment.center,
                  color: const Color(0xFFF0F2F5),
                  child: Text(prefix!, style: const TextStyle(color: Color(0xFF3B4A60), fontSize: 15)),
                ),
              Expanded(
                child: TextField(
                  controller: controller,
                  keyboardType: keyboardType,
                  inputFormatters: formatters,
                  textCapitalization: capitalization,
                  onChanged: (_) => onChanged?.call(),
                  style: const TextStyle(color: _navy, fontSize: 15.5),
                  decoration: InputDecoration(
                    hintText: hint,
                    hintStyle: const TextStyle(color: Color(0xFF9AA5B5)),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                    filled: false,
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                  ),
                ),
              ),
              if (suffix != null) Padding(padding: const EdgeInsets.only(right: 12), child: suffix),
            ],
          ),
        ),
        if (hasError)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Row(
              children: [
                const Icon(Icons.error_rounded, color: OvanieColors.danger, size: 18),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(error!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13)),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

/// Aperçu de la carte : il reflète exactement la saisie en cours.
class _CardPreview extends StatelessWidget {
  const _CardPreview({required this.brand, required this.digits, required this.holder, required this.expiry});

  final String brand;
  final String digits;
  final String holder;
  final String expiry;

  @override
  Widget build(BuildContext context) {
    final slots = digits.length <= 16 ? 16 : ((digits.length + 3) ~/ 4) * 4;
    final padded = digits.padRight(slots, '•');
    final groups = [for (var i = 0; i < slots; i += 4) padded.substring(i, i + 4)];
    final brandLabel = switch (brand) {
      'visa' => 'VISA',
      'mastercard' => 'mastercard',
      _ => '',
    };
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 18),
      child: AspectRatio(
        aspectRatio: 2.02,
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF0B1F45), Color(0xFF123A7A), Color(0xFF0B2552)],
            ),
            boxShadow: const [BoxShadow(color: Color(0x330A1B3D), blurRadius: 14, offset: Offset(0, 6))],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 30,
                child: Text(
                  brandLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w900,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ),
              const Spacer(),
              FittedBox(
                fit: BoxFit.scaleDown,
                child: Text(
                  groups.join('   '),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 21,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 1.6,
                  ),
                ),
              ),
              const Spacer(),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: Text(
                      holder.isEmpty ? 'NOM DU TITULAIRE' : holder.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: holder.isEmpty ? 0.55 : 1),
                        fontSize: 13,
                        letterSpacing: 1.5,
                      ),
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'EXPIRE LE',
                        style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 9, letterSpacing: 1),
                      ),
                      Text(
                        expiry.isEmpty ? 'MM/AA' : expiry,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: expiry.isEmpty ? 0.55 : 1),
                          fontSize: 14,
                          letterSpacing: 1.2,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CardNumberFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final buffer = StringBuffer();
    for (var i = 0; i < digits.length; i++) {
      if (i > 0 && i % 4 == 0) buffer.write(' ');
      buffer.write(digits[i]);
    }
    final text = buffer.toString();
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}

class _ExpiryFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    final digits = newValue.text.replaceAll(RegExp(r'\D'), '');
    final text = digits.length <= 2 ? digits : '${digits.substring(0, 2)}/${digits.substring(2)}';
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}
