import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../auth/domain/session_store.dart';
import '../../auth/presentation/auth_branding.dart' show authCountries, showAuthCountrySheet;
import '../../cart/domain/cart_store.dart';
import '../../payments/presentation/payment_ui.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';
import 'delete_account_screen.dart';
import 'notification_preferences_screen.dart';
import 'security_screen.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _iconColor = Color(0xFF2E3A4D);
const _pageBackground = Color(0xFFF6F8FB);

/// Sépare un numéro enregistré (« +2250708123456 ») en indicatif + numéro.
(String, String) splitPhone(String raw) {
  final compact = raw.replaceAll(RegExp(r'[\s.-]'), '');
  for (final country in authCountries) {
    final code = country.$1;
    final bare = code.substring(1);
    if (compact.startsWith(code)) return (code, compact.substring(code.length));
    if (compact.startsWith('00$bare')) return (code, compact.substring(bare.length + 2));
  }
  return ('+225', compact.replaceAll(RegExp(r'\D'), ''));
}

/// « +2250708123456 » → « +225 07 08 12 34 56 ».
String formatPhone(String raw) {
  if (raw.trim().isEmpty) return '';
  final (code, number) = splitPhone(raw);
  final digits = number.replaceAll(RegExp(r'\D'), '');
  final groups = <String>[];
  for (var i = 0; i < digits.length; i += 2) {
    groups.add(digits.substring(i, i + 2 > digits.length ? digits.length : i + 2));
  }
  return '$code ${groups.join(' ')}'.trim();
}

class ProfileScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const ProfileScreen({super.key, this.onOpenHome, this.onOpenCategories, this.onOpenCart, this.onOpenFavorites});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _repository = const ClientAccountRepository();
  ClientProfile? _profile;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = _profile == null;
        _error = null;
      });
    }
    try {
      final profile = await _repository.profile();
      if (!mounted) return;
      setState(() {
        _profile = profile;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = ApiClient.friendlyError(error);
      });
    }
  }

  String _displayName(ClientProfile profile) {
    final combined = '${profile.firstName} ${profile.lastName}'.trim();
    return combined.isNotEmpty ? combined : profile.name.trim();
  }

  bool _isIncomplete(ClientProfile profile) =>
      _displayName(profile).isEmpty || profile.phone.trim().isEmpty || profile.email.trim().isEmpty;

  String _initials(ClientProfile profile) {
    final parts = _displayName(profile).split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return 'O';
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'.toUpperCase();
  }

  Future<void> _editProfile() async {
    final profile = _profile;
    if (profile == null) return;
    final updated = await showModalBottomSheet<ClientProfile>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (_) => _EditProfileSheet(profile: profile, repository: _repository),
    );
    if (updated == null || !mounted) return;
    setState(() => _profile = updated);
    showOvanieToast(context, 'Profil OVANIE mis à jour.');
  }

  Future<void> _push(Widget screen) async {
    await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        final profile = _profile;
        return Scaffold(
          backgroundColor: _pageBackground,
          bottomNavigationBar: const OvanieBottomNavigation(selectedTab: OvanieMainTab.account),
          appBar: AppBar(
            backgroundColor: _pageBackground,
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            centerTitle: true,
            leading: IconButton(
              onPressed: () => Navigator.maybePop(context),
              tooltip: 'Retour',
              icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
            ),
            title: const Text(
              'Mon profil',
              style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
            ),
          ),
          body: _loading
              ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
              : profile == null
              ? _ErrorState(message: _error ?? 'Votre profil est indisponible.', onRetry: _load)
              : RefreshIndicator(
                  color: OvanieColors.orange,
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(14, 4, 14, 24),
                    children: _isIncomplete(profile) ? _incomplete(profile) : _complete(profile),
                  ),
                ),
        );
      },
    );
  }

  List<Widget> _complete(ClientProfile profile) {
    final name = _displayName(profile);
    return [
      _Card(
        padding: const EdgeInsets.fromLTRB(14, 16, 14, 14),
        child: Column(
          children: [
            Container(
              width: 78,
              height: 78,
              alignment: Alignment.center,
              decoration: const BoxDecoration(color: OvanieColors.orange, shape: BoxShape.circle),
              child: Text(
                _initials(profile),
                style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w800),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              name,
              textAlign: TextAlign.center,
              style: const TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              decoration: BoxDecoration(color: const Color(0xFFEDF0F4), borderRadius: BorderRadius.circular(99)),
              child: const Text(
                'Client OVANIE',
                style: TextStyle(color: Color(0xFF4B586C), fontSize: 13, fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 44,
              child: OutlinedButton(
                onPressed: _editProfile,
                style: OutlinedButton.styleFrom(
                  foregroundColor: _navy,
                  side: const BorderSide(color: _navy, width: 1.4),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                ),
                child: const Text(
                  'Modifier mes informations',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      _Section(
        title: 'Informations personnelles',
        children: [
          _InfoRow(icon: Icons.person_outline_rounded, label: 'Nom complet', value: name, onTap: _editProfile),
          _InfoRow(
            icon: Icons.phone_outlined,
            label: 'Numéro de téléphone',
            value: formatPhone(profile.phone),
            onTap: _editProfile,
          ),
          _InfoRow(
            icon: Icons.mail_outline_rounded,
            label: 'Adresse e-mail',
            value: profile.email,
            onTap: _editProfile,
          ),
          _InfoRow(
            icon: Icons.lock_outline_rounded,
            label: 'Mot de passe',
            value: '••••••••',
            onTap: () => _push(const SecurityScreen()),
          ),
        ],
      ),
      const SizedBox(height: 12),
      _Section(
        title: 'Préférences',
        flush: true,
        children: [
          _LinkRow(
            icon: Icons.notifications_none_rounded,
            title: 'Notifications',
            onTap: () => _push(const NotificationPreferencesScreen()),
          ),
        ],
      ),
      const SizedBox(height: 12),
      _Section(
        title: 'Sécurité',
        flush: true,
        children: [
          _LinkRow(
            icon: Icons.verified_user_outlined,
            title: 'Mot de passe et sécurité',
            onTap: () => _push(const SecurityScreen()),
          ),
          _LinkRow(
            icon: Icons.laptop_rounded,
            title: 'Appareils connectés',
            onTap: () => _push(const SecurityScreen()),
          ),
          _LinkRow(
            icon: Icons.delete_outline_rounded,
            title: 'Supprimer mon compte',
            danger: true,
            onTap: () => _push(const DeleteAccountScreen()),
          ),
        ],
      ),
    ];
  }

  List<Widget> _incomplete(ClientProfile profile) {
    final name = _displayName(profile);
    return [
      Container(
        padding: const EdgeInsets.fromLTRB(14, 18, 14, 14),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF4E8),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFF4BE6E)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  width: 60,
                  height: 60,
                  decoration: const BoxDecoration(color: Color(0xFFFCE6CF), shape: BoxShape.circle),
                  child: const Icon(Icons.person_outline_rounded, color: Color(0xFF9A3F0B), size: 32),
                ),
                const SizedBox(width: 14),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Votre profil est incomplet',
                        style: TextStyle(color: Color(0xFF8C3A0C), fontSize: 17, fontWeight: FontWeight.w800),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Ajoutez vos informations personnelles pour mieux gérer votre compte OVANIE.',
                        style: TextStyle(color: Color(0xFF5E6676), fontSize: 13.5, height: 1.4),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            SizedBox(
              height: 46,
              child: FilledButton(
                onPressed: _editProfile,
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                child: const Text('Compléter mon profil', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: 12),
      _Section(
        title: 'Informations personnelles',
        flush: true,
        children: [
          name.isEmpty
              ? _MissingRow(
                  icon: Icons.person_outline_rounded,
                  field: 'Nom et prénom',
                  missing: 'non renseignés',
                  onTap: _editProfile,
                )
              : _InfoRow(icon: Icons.person_outline_rounded, label: 'Nom complet', value: name, onTap: _editProfile),
          profile.phone.trim().isEmpty
              ? _MissingRow(
                  icon: Icons.phone_outlined,
                  field: 'Numéro de téléphone',
                  missing: 'non renseigné',
                  onTap: _editProfile,
                )
              : _InfoRow(
                  icon: Icons.phone_outlined,
                  label: 'Numéro de téléphone',
                  value: formatPhone(profile.phone),
                  onTap: _editProfile,
                ),
          profile.email.trim().isEmpty
              ? _MissingRow(
                  icon: Icons.mail_outline_rounded,
                  field: 'Adresse e-mail',
                  missing: 'non renseignée',
                  onTap: _editProfile,
                )
              : _InfoRow(
                  icon: Icons.mail_outline_rounded,
                  label: 'Adresse e-mail',
                  value: profile.email,
                  onTap: _editProfile,
                ),
        ],
      ),
      const SizedBox(height: 12),
      _Section(
        title: 'Préférences',
        flush: true,
        children: [
          _LinkRow(
            icon: Icons.notifications_none_rounded,
            title: 'Aucune préférence configurée pour le moment.',
            muted: true,
            onTap: () => _push(const NotificationPreferencesScreen()),
          ),
        ],
      ),
      const SizedBox(height: 12),
      _Section(
        title: 'Sécurité',
        flush: true,
        children: [
          // La suppression du compte reste accessible depuis cet écran,
          // même tant que le profil n'est pas complet.
          _LinkRow(
            icon: Icons.verified_user_outlined,
            title: 'Configurez votre sécurité après avoir complété votre profil.',
            muted: true,
            onTap: () => _push(const SecurityScreen()),
          ),
        ],
      ),
    ];
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.child, this.padding = EdgeInsets.zero});

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) => Container(
    padding: padding,
    clipBehavior: Clip.antiAlias,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: _line),
      boxShadow: const [BoxShadow(color: Color(0x0A102A4C), blurRadius: 8, offset: Offset(0, 2))],
    ),
    child: child,
  );
}

/// Carte titrée. `flush` : séparateurs pleine largeur (lignes de navigation).
class _Section extends StatelessWidget {
  const _Section({required this.title, required this.children, this.flush = false});

  final String title;
  final List<Widget> children;
  final bool flush;

  @override
  Widget build(BuildContext context) {
    return _Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            child: Text(
              title,
              style: const TextStyle(color: _navy, fontSize: 17, fontWeight: FontWeight.w800),
            ),
          ),
          const Divider(height: 1, thickness: 1, color: _line),
          for (var i = 0; i < children.length; i++) ...[
            children[i],
            if (i != children.length - 1)
              Divider(height: 1, thickness: 1, color: _line, indent: flush ? 0 : 14, endIndent: flush ? 0 : 14),
          ],
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label, required this.value, required this.onTap});

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        child: Row(
          children: [
            Icon(icon, color: _iconColor, size: 23),
            const SizedBox(width: 18),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: const TextStyle(color: _muted, fontSize: 12.5)),
                  const SizedBox(height: 2),
                  Text(
                    value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: _navy, fontSize: 15.5, fontWeight: FontWeight.w500),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MissingRow extends StatelessWidget {
  const _MissingRow({required this.icon, required this.field, required this.missing, required this.onTap});

  final IconData icon;
  final String field;
  final String missing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 15, 10, 15),
        child: Row(
          children: [
            Icon(icon, color: const Color(0xFF4A586E), size: 23),
            const SizedBox(width: 18),
            Expanded(
              child: Text.rich(
                TextSpan(
                  text: field,
                  style: const TextStyle(color: Color(0xFF4A586E)),
                  children: [
                    TextSpan(
                      text: ' $missing',
                      style: const TextStyle(color: Color(0xFF8B96A8)),
                    ),
                  ],
                ),
                style: const TextStyle(fontSize: 15),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8), size: 22),
          ],
        ),
      ),
    );
  }
}

class _LinkRow extends StatelessWidget {
  const _LinkRow({
    required this.icon,
    required this.title,
    required this.onTap,
    this.danger = false,
    this.muted = false,
  });

  final IconData icon;
  final String title;
  final VoidCallback onTap;
  final bool danger;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    final color = danger ? OvanieColors.danger : (muted ? const Color(0xFF5E6B80) : _navy);
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 10, 14),
        child: Row(
          children: [
            Icon(icon, color: danger ? OvanieColors.danger : _iconColor, size: 23),
            const SizedBox(width: 18),
            Expanded(
              child: Text(
                title,
                style: TextStyle(color: color, fontSize: 15, fontWeight: FontWeight.w500, height: 1.35),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: Color(0xFF8A96A8), size: 22),
          ],
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_rounded, color: _muted, size: 52),
            const SizedBox(height: 14),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: _muted, height: 1.45),
            ),
            const SizedBox(height: 18),
            PaymentButton(label: 'Réessayer', onPressed: onRetry),
          ],
        ),
      ),
    );
  }
}

class _EditProfileSheet extends StatefulWidget {
  const _EditProfileSheet({required this.profile, required this.repository});

  final ClientProfile profile;
  final ClientAccountRepository repository;

  @override
  State<_EditProfileSheet> createState() => _EditProfileSheetState();
}

class _EditProfileSheetState extends State<_EditProfileSheet> {
  late final _first = TextEditingController(text: widget.profile.firstName);
  late final _last = TextEditingController(text: widget.profile.lastName);
  late final _email = TextEditingController(text: widget.profile.email);
  late final TextEditingController _phone;
  late final TextEditingController _whatsapp;
  late String _phoneCode;
  late String _whatsappCode;
  final _formKey = GlobalKey<FormState>();
  bool _saving = false;
  String? _serverError;

  @override
  void initState() {
    super.initState();
    final phone = splitPhone(widget.profile.phone);
    final whatsapp = splitPhone(widget.profile.whatsappPhone);
    _phoneCode = phone.$1;
    _whatsappCode = whatsapp.$1;
    _phone = TextEditingController(text: _spaced(phone.$2));
    _whatsapp = TextEditingController(text: _spaced(whatsapp.$2));
  }

  static String _spaced(String digits) {
    final clean = digits.replaceAll(RegExp(r'\D'), '');
    final buffer = StringBuffer();
    for (var i = 0; i < clean.length; i++) {
      if (i > 0 && i.isEven) buffer.write(' ');
      buffer.write(clean[i]);
    }
    return buffer.toString();
  }

  @override
  void dispose() {
    _first.dispose();
    _last.dispose();
    _email.dispose();
    _phone.dispose();
    _whatsapp.dispose();
    super.dispose();
  }

  String _digits(TextEditingController controller) => controller.text.replaceAll(RegExp(r'\D'), '');

  String? _phoneValidator(String? value, {bool optional = false}) {
    final digits = (value ?? '').replaceAll(RegExp(r'\D'), '');
    if (digits.isEmpty) return optional ? null : 'Renseignez votre numéro de téléphone';
    if (digits.length < 8 || digits.length > 12) return 'Numéro de téléphone invalide';
    return null;
  }

  Future<void> _save() async {
    FocusScope.of(context).unfocus();
    setState(() => _serverError = null);
    if (!(_formKey.currentState?.validate() ?? false) || _saving) return;
    setState(() => _saving = true);
    try {
      final whatsappDigits = _digits(_whatsapp);
      final updated = await widget.repository.updateProfile(
        firstName: _first.text.trim(),
        lastName: _last.text.trim(),
        email: _email.text.trim(),
        phone: '$_phoneCode${_digits(_phone)}',
        whatsappPhone: whatsappDigits.isEmpty ? '' : '$_whatsappCode$whatsappDigits',
      );
      await SessionStore.instance.updateUser(updated.toSessionJson());
      if (mounted) Navigator.of(context).pop(updated);
    } catch (error) {
      if (mounted) setState(() => _serverError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottom),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(color: const Color(0xFFD5DBE4), borderRadius: BorderRadius.circular(99)),
                  ),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Modifier mon profil',
                        style: TextStyle(color: _navy, fontSize: 21, fontWeight: FontWeight.w800),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(),
                      tooltip: 'Fermer',
                      icon: const Icon(Icons.close_rounded, color: Color(0xFF4A586E), size: 26),
                    ),
                  ],
                ),
                _FieldLabel('Prénom'),
                _SheetField(
                  controller: _first,
                  textCapitalization: TextCapitalization.words,
                  validator: (v) => (v ?? '').trim().isEmpty ? 'Renseignez votre prénom' : null,
                ),
                _FieldLabel('Nom'),
                _SheetField(
                  controller: _last,
                  textCapitalization: TextCapitalization.words,
                  validator: (v) => (v ?? '').trim().isEmpty ? 'Renseignez votre nom' : null,
                ),
                _FieldLabel('Adresse e-mail'),
                _SheetField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  validator: (v) {
                    final text = (v ?? '').trim();
                    if (text.isEmpty) return 'Renseignez votre adresse e-mail';
                    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(text)) return 'Adresse e-mail invalide';
                    return null;
                  },
                ),
                _FieldLabel('Numéro de téléphone'),
                _PhoneInput(
                  controller: _phone,
                  code: _phoneCode,
                  onCodeChanged: (code) => setState(() => _phoneCode = code),
                  validator: _phoneValidator,
                ),
                _FieldLabel('Téléphone WhatsApp', optional: true),
                _PhoneInput(
                  controller: _whatsapp,
                  code: _whatsappCode,
                  onCodeChanged: (code) => setState(() => _whatsappCode = code),
                  validator: (v) => _phoneValidator(v, optional: true),
                ),
                if (_serverError != null) ...[
                  const SizedBox(height: 12),
                  Text(_serverError!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5)),
                ],
                const SizedBox(height: 18),
                PaymentButton(label: 'Enregistrer', loading: _saving, onPressed: _save),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _FieldLabel extends StatelessWidget {
  const _FieldLabel(this.text, {this.optional = false});

  final String text;
  final bool optional;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 12, bottom: 6),
    child: Text.rich(
      TextSpan(
        text: text,
        style: const TextStyle(color: _navy, fontWeight: FontWeight.w700),
        children: [
          if (optional)
            const TextSpan(
              text: ' (optionnel)',
              style: TextStyle(color: _muted, fontWeight: FontWeight.w400),
            ),
        ],
      ),
      style: const TextStyle(fontSize: 14),
    ),
  );
}

InputDecoration _sheetDecoration({String? errorText}) {
  OutlineInputBorder border(Color color, [double width = 1.2]) => OutlineInputBorder(
    borderRadius: BorderRadius.circular(9),
    borderSide: BorderSide(color: color, width: width),
  );
  return InputDecoration(
    isDense: true,
    filled: true,
    fillColor: Colors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    enabledBorder: border(const Color(0xFFD5DCE6)),
    focusedBorder: border(OvanieColors.navy, 1.4),
    errorBorder: border(OvanieColors.danger),
    focusedErrorBorder: border(OvanieColors.danger, 1.4),
    errorStyle: const TextStyle(color: OvanieColors.danger, fontSize: 13),
    errorText: errorText,
  );
}

/// Champ texte de la feuille : en erreur, fond rosé + pictogramme « ! ».
class _SheetField extends StatefulWidget {
  const _SheetField({
    required this.controller,
    required this.validator,
    this.keyboardType,
    this.textCapitalization = TextCapitalization.none,
  });

  final TextEditingController controller;
  final String? Function(String?) validator;
  final TextInputType? keyboardType;
  final TextCapitalization textCapitalization;

  @override
  State<_SheetField> createState() => _SheetFieldState();
}

class _SheetFieldState extends State<_SheetField> {
  @override
  Widget build(BuildContext context) {
    return FormField<String>(
      initialValue: widget.controller.text,
      validator: (_) => widget.validator(widget.controller.text),
      builder: (field) {
        final hasError = field.hasError;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: widget.controller,
              keyboardType: widget.keyboardType,
              textCapitalization: widget.textCapitalization,
              onChanged: (value) {
                field.didChange(value);
                if (hasError) field.validate();
              },
              style: const TextStyle(color: _navy, fontSize: 15.5),
              decoration: _sheetDecoration().copyWith(
                fillColor: hasError ? const Color(0xFFFFF0F0) : Colors.white,
                enabledBorder: hasError
                    ? OutlineInputBorder(
                        borderRadius: BorderRadius.circular(9),
                        borderSide: const BorderSide(color: OvanieColors.danger, width: 1.2),
                      )
                    : null,
                suffixIcon: hasError
                    ? const Icon(Icons.error_outline_rounded, color: OvanieColors.danger, size: 24)
                    : null,
              ),
            ),
            if (hasError) ...[
              const SizedBox(height: 5),
              Text(field.errorText!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5)),
            ],
          ],
        );
      },
    );
  }
}

class _PhoneInput extends StatelessWidget {
  const _PhoneInput({required this.controller, required this.code, required this.onCodeChanged, this.validator});

  final TextEditingController controller;
  final String code;
  final ValueChanged<String> onCodeChanged;
  final String? Function(String?)? validator;

  @override
  Widget build(BuildContext context) {
    return FormField<String>(
      validator: (_) => validator?.call(controller.text),
      builder: (field) {
        final borderColor = field.hasError ? OvanieColors.danger : const Color(0xFFD5DCE6);
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 50,
              decoration: BoxDecoration(
                color: field.hasError ? const Color(0xFFFFF0F0) : Colors.white,
                borderRadius: BorderRadius.circular(9),
                border: Border.all(color: borderColor, width: 1.2),
              ),
              child: Row(
                children: [
                  InkWell(
                    onTap: () async {
                      final picked = await showAuthCountrySheet(context, code);
                      if (picked != null) onCodeChanged(picked);
                    },
                    borderRadius: const BorderRadius.horizontal(left: Radius.circular(9)),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: Row(
                        children: [
                          Text(code, style: const TextStyle(color: _navy, fontSize: 15.5)),
                          const SizedBox(width: 8),
                          const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF4A586E), size: 22),
                        ],
                      ),
                    ),
                  ),
                  Container(width: 1, height: 30, color: const Color(0xFFD5DCE6)),
                  Expanded(
                    child: TextField(
                      controller: controller,
                      keyboardType: TextInputType.phone,
                      inputFormatters: [
                        FilteringTextInputFormatter.allow(RegExp(r'[0-9 ]')),
                        LengthLimitingTextInputFormatter(17),
                      ],
                      onChanged: (_) {
                        if (field.hasError) field.validate();
                      },
                      style: const TextStyle(color: _navy, fontSize: 15.5),
                      decoration: const InputDecoration(
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        filled: false,
                        isDense: true,
                        contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (field.hasError) ...[
              const SizedBox(height: 5),
              Text(field.errorText!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5)),
            ],
          ],
        );
      },
    );
  }
}
