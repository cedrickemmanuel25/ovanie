import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/ovanie_bottom_navigation.dart';
import '../../shell/domain/main_navigation_store.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../support/presentation/support_center_screen.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';
import 'delete_account_screen.dart';
import 'notification_preferences_screen.dart';
import 'security_screen.dart';

class ProfileScreen extends StatefulWidget {
  final VoidCallback? onOpenHome;
  final VoidCallback? onOpenCategories;
  final VoidCallback? onOpenCart;
  final VoidCallback? onOpenFavorites;

  const ProfileScreen({
    super.key,
    this.onOpenHome,
    this.onOpenCategories,
    this.onOpenCart,
    this.onOpenFavorites,
  });

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  static const _navy = Color(0xFF071B53);
  static const _muted = Color(0xFF52658F);
  static const _line = Color(0xFFE2E7F0);
  static const _blue = Color(0xFF1464EB);

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
        _loading = true;
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

  bool get _isIncomplete {
    final profile = _profile;
    if (profile == null) return true;
    final hasName = _displayName(profile).trim().isNotEmpty;
    return !hasName || profile.phone.trim().isEmpty || profile.email.trim().isEmpty;
  }

  String _displayName(ClientProfile profile) {
    final combined = '${profile.firstName} ${profile.lastName}'.trim();
    return combined.isNotEmpty ? combined : profile.name.trim();
  }

  String _initials(ClientProfile profile) {
    final name = _displayName(profile);
    if (name.isEmpty) return 'OV';
    final parts = name.split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'.toUpperCase();
  }

  Future<void> _editProfile() async {
    final profile = _profile;
    if (profile == null) return;

    final first = TextEditingController(text: profile.firstName);
    final last = TextEditingController(text: profile.lastName);
    final email = TextEditingController(text: profile.email);
    final phone = TextEditingController(text: profile.phone);
    final whatsapp = TextEditingController(text: profile.whatsappPhone);
    final formKey = GlobalKey<FormState>();
    bool saving = false;

    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            Future<void> save() async {
              if (!(formKey.currentState?.validate() ?? false) || saving) return;
              setSheetState(() => saving = true);
              try {
                final updated = await _repository.updateProfile(
                  firstName: first.text,
                  lastName: last.text,
                  email: email.text,
                  phone: phone.text,
                  whatsappPhone: whatsapp.text,
                );
                await SessionStore.instance.updateUser(updated.toSessionJson());
                if (!mounted) return;
                setState(() => _profile = updated);
                if (sheetContext.mounted) Navigator.of(sheetContext).pop(true);
              } catch (error) {
                if (sheetContext.mounted) {
                  ScaffoldMessenger.of(sheetContext).showSnackBar(
                    SnackBar(content: Text(ApiClient.friendlyError(error))),
                  );
                }
              } finally {
                if (sheetContext.mounted) setSheetState(() => saving = false);
              }
            }

            final bottom = MediaQuery.of(sheetContext).viewInsets.bottom;
            return Padding(
              padding: EdgeInsets.fromLTRB(20, 14, 20, 20 + bottom),
              child: SingleChildScrollView(
                child: Form(
                  key: formKey,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Center(
                        child: Container(
                          width: 42,
                          height: 4,
                          decoration: BoxDecoration(
                            color: const Color(0xFFD8DEE9),
                            borderRadius: BorderRadius.circular(99),
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),
                      const Text(
                        'Modifier mon profil',
                        style: TextStyle(color: _navy, fontSize: 22, fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 18),
                      Row(
                        children: [
                          Expanded(child: _editField(first, 'Prénom', Icons.person_outline_rounded)),
                          const SizedBox(width: 12),
                          Expanded(child: _editField(last, 'Nom', Icons.person_outline_rounded)),
                        ],
                      ),
                      const SizedBox(height: 12),
                      _editField(
                        email,
                        'Adresse e-mail',
                        Icons.mail_outline_rounded,
                        keyboardType: TextInputType.emailAddress,
                        validator: (value) {
                          final text = value?.trim() ?? '';
                          if (text.isEmpty || !text.contains('@')) return 'Adresse e-mail invalide';
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      _editField(phone, 'Numéro de téléphone', Icons.phone_outlined, keyboardType: TextInputType.phone),
                      const SizedBox(height: 12),
                      _editField(
                        whatsapp,
                        'Téléphone WhatsApp (optionnel)',
                        Icons.chat_bubble_outline_rounded,
                        keyboardType: TextInputType.phone,
                      ),
                      const SizedBox(height: 18),
                      SizedBox(
                        width: double.infinity,
                        height: 52,
                        child: FilledButton(
                          onPressed: saving ? null : save,
                          style: FilledButton.styleFrom(
                            backgroundColor: OvanieColors.orange,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
                          ),
                          child: saving
                              ? const SizedBox.square(
                                  dimension: 20,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Enregistrer', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w900)),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );

    first.dispose();
    last.dispose();
    email.dispose();
    phone.dispose();
    whatsapp.dispose();

    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Profil OVANIE mis à jour.')));
    }
  }

  Widget _editField(
    TextEditingController controller,
    String label,
    IconData icon, {
    TextInputType? keyboardType,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      validator: validator,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: _navy),
      ),
    );
  }

  void _goRoot(VoidCallback? callback) {
    if (callback == null) return;
    Navigator.of(context).pop();
    callback();
  }

  void _openSupport() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SupportCenterScreen()));
  }

  void _openSecurity() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const SecurityScreen()));
  }

  void _openDeleteAccount() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const DeleteAccountScreen()));
  }

  void _openNotificationPreferences() {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const NotificationPreferencesScreen()));
  }

  void _showSimpleChoice(String title, String value) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w900)),
              const SizedBox(height: 14),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.check_circle_rounded, color: OvanieColors.orange),
                title: Text(value, style: const TextStyle(color: _navy, fontWeight: FontWeight.w700)),
                onTap: () => Navigator.pop(context),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: CartStore.instance,
      builder: (context, _) {
        return Scaffold(
          backgroundColor: Colors.white,
          bottomNavigationBar: const OvanieBottomNavigation(
            selectedTab: OvanieMainTab.account,
          ),
          body: SafeArea(
            bottom: false,
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
                : _error != null
                    ? _buildError()
                    : RefreshIndicator(
                        color: OvanieColors.orange,
                        onRefresh: _load,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
                          padding: const EdgeInsets.fromLTRB(18, 14, 18, 26),
                          children: [
                            _buildHeader(),
                            const SizedBox(height: 22),
                            if (_isIncomplete) _buildIncompleteProfile() else _buildCompleteProfile(),
                          ],
                        ),
                      ),
          ),
        );
      },
    );
  }

  Widget _buildHeader() {
    return Row(
      children: [
        IconButton(
          onPressed: () => Navigator.maybePop(context),
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints.tightFor(width: 42, height: 42),
          icon: const Icon(Icons.arrow_back_rounded, color: _navy, size: 29),
        ),
        const SizedBox(width: 8),
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Mon profil',
                style: TextStyle(color: _navy, fontSize: 27, height: 1.05, fontWeight: FontWeight.w900, letterSpacing: -0.4),
              ),
              SizedBox(height: 7),
              Text('Gérez vos informations personnelles', style: TextStyle(color: _muted, fontSize: 13.2, height: 1.3)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildCompleteProfile() {
    final profile = _profile!;
    final name = _displayName(profile);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InkWell(
          onTap: _editProfile,
          borderRadius: BorderRadius.circular(12),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 16, 12, 16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _line),
            ),
            child: Row(
              children: [
                Container(
                  width: 88,
                  height: 88,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: LinearGradient(colors: [Color(0xFF0B2770), Color(0xFF091750)]),
                  ),
                  child: Text(
                    _initials(profile),
                    style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: 20),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name.isEmpty ? 'Client OVANIE' : name, style: const TextStyle(color: _navy, fontSize: 20.5, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(color: OvanieColors.orange, borderRadius: BorderRadius.circular(6)),
                        child: const Text('Client', style: TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w800)),
                      ),
                      if (profile.phone.trim().isNotEmpty) ...[
                        const SizedBox(height: 8),
                        _compactContact(Icons.phone_outlined, profile.phone.trim()),
                      ],
                      if (profile.email.trim().isNotEmpty) ...[
                        const SizedBox(height: 6),
                        _compactContact(Icons.mail_outline_rounded, profile.email.trim()),
                      ],
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: _navy, size: 28),
              ],
            ),
          ),
        ),
        const SizedBox(height: 22),
        const _SectionTitle('Informations personnelles'),
        const SizedBox(height: 10),
        _ProfileGroup(
          children: [
            _ProfileRow(
              icon: Icons.person_outline_rounded,
              iconColor: const Color(0xFF1464EB),
              iconBackground: const Color(0xFFEEF4FF),
              title: 'Informations personnelles',
              subtitle: 'Nom, prénom, date de naissance, etc.',
              onTap: _editProfile,
            ),
            _ProfileRow(
              icon: Icons.phone_outlined,
              iconColor: const Color(0xFF10A94D),
              iconBackground: const Color(0xFFECFAF1),
              title: 'Numéro de téléphone',
              subtitle: profile.phone.trim().isEmpty ? 'Non renseigné' : profile.phone.trim(),
              onTap: _editProfile,
            ),
            _ProfileRow(
              icon: Icons.mail_outline_rounded,
              iconColor: const Color(0xFF7038F5),
              iconBackground: const Color(0xFFF3EFFF),
              title: 'Adresse e-mail',
              subtitle: profile.email.trim().isEmpty ? 'Non renseignée' : profile.email.trim(),
              onTap: _editProfile,
            ),
            _ProfileRow(
              icon: Icons.lock_outline_rounded,
              iconColor: OvanieColors.orange,
              iconBackground: const Color(0xFFFFF3E9),
              title: 'Mot de passe',
              subtitle: 'Modifier votre mot de passe',
              onTap: _openSecurity,
              divider: false,
            ),
          ],
        ),
        const SizedBox(height: 22),
        const _SectionTitle('Préférences'),
        const SizedBox(height: 10),
        _ProfileGroup(
          children: [
            _ProfileRow(
              icon: Icons.notifications_none_rounded,
              iconColor: const Color(0xFF7038F5),
              iconBackground: const Color(0xFFF3EFFF),
              title: 'Préférences de notifications',
              subtitle: 'Gérez les types de notifications que vous recevez',
              onTap: _openNotificationPreferences,
            ),
            _ProfileRow(
              icon: Icons.language_rounded,
              iconColor: const Color(0xFF246BE9),
              iconBackground: const Color(0xFFEEF4FF),
              title: 'Langue',
              subtitle: 'Français',
              onTap: () => _showSimpleChoice('Langue', 'Français'),
            ),
            _ProfileRow(
              icon: Icons.dark_mode_outlined,
              iconColor: _navy,
              iconBackground: const Color(0xFFF2F4F8),
              title: 'Thème',
              subtitle: 'Clair',
              onTap: () => _showSimpleChoice('Thème', 'Clair'),
              divider: false,
            ),
          ],
        ),
        const SizedBox(height: 22),
        const _SectionTitle('Sécurité'),
        const SizedBox(height: 10),
        _ProfileGroup(
          children: [
            _ProfileRow(
              icon: Icons.verified_user_outlined,
              iconColor: const Color(0xFF10A94D),
              iconBackground: const Color(0xFFECFAF1),
              title: 'Authentification à deux facteurs',
              subtitle: 'Renforcez la sécurité de votre compte',
              trailingText: 'Désactivée',
              trailingTextColor: OvanieColors.orange,
              onTap: _openSecurity,
            ),
            _ProfileRow(
              icon: Icons.desktop_windows_outlined,
              iconColor: const Color(0xFF246BE9),
              iconBackground: const Color(0xFFEEF4FF),
              title: 'Sessions actives',
              subtitle: 'Gérez vos sessions sur tous vos appareils',
              onTap: _openSecurity,
            ),
            _ProfileRow(
              icon: Icons.delete_outline_rounded,
              iconColor: const Color(0xFFED1B24),
              iconBackground: const Color(0xFFFFEEEE),
              title: 'Supprimer mon compte',
              subtitle: 'Supprimer définitivement votre compte et vos données',
              onTap: _openDeleteAccount,
              divider: false,
            ),
          ],
        ),
        const SizedBox(height: 22),
        _HelpCard(onTap: _openSupport),
      ],
    );
  }

  Widget _compactContact(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, size: 18, color: _navy),
        const SizedBox(width: 8),
        Flexible(child: Text(text, overflow: TextOverflow.ellipsis, style: const TextStyle(color: _navy, fontSize: 12.7, fontWeight: FontWeight.w500))),
      ],
    );
  }

  Widget _buildIncompleteProfile() {
    final profile = _profile!;
    final name = _displayName(profile);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _line),
          ),
          child: Row(
            children: [
              SizedBox(
                width: 108,
                height: 89,
                child: Image.asset(
                  'assets/images/profile_incomplete_illustration.png',
                  fit: BoxFit.contain,
                  errorBuilder: (_, __, ___) => const Icon(Icons.account_circle_outlined, color: Color(0xFFC6D1F8), size: 72),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Votre profil est incomplet', style: TextStyle(color: _navy, fontSize: 19.5, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 10),
                    const Text(
                      'Ajoutez vos informations personnelles\npour mieux gérer votre compte OVANIE.',
                      style: TextStyle(color: _muted, fontSize: 13.4, height: 1.4),
                    ),
                    const SizedBox(height: 14),
                    SizedBox(
                      height: 40,
                      child: FilledButton(
                        onPressed: _editProfile,
                        style: FilledButton.styleFrom(
                          backgroundColor: OvanieColors.orange,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 17),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text('Compléter mon profil', style: TextStyle(fontWeight: FontWeight.w900)),
                            SizedBox(width: 10),
                            Icon(Icons.arrow_forward_rounded, size: 20),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 22),
        const _SectionTitle('Informations personnelles'),
        const SizedBox(height: 10),
        _ProfileGroup(
          children: [
            _ProfileRow(
              icon: Icons.person_outline_rounded,
              iconColor: const Color(0xFF1464EB),
              iconBackground: const Color(0xFFEEF4FF),
              title: name.isEmpty ? 'Nom et prénom non renseignés' : name,
              onTap: _editProfile,
              showChevron: false,
            ),
            _ProfileRow(
              icon: Icons.phone_outlined,
              iconColor: const Color(0xFF10A94D),
              iconBackground: const Color(0xFFECFAF1),
              title: profile.phone.trim().isEmpty ? 'Numéro de téléphone non renseigné' : profile.phone.trim(),
              onTap: _editProfile,
              showChevron: false,
            ),
            _ProfileRow(
              icon: Icons.mail_outline_rounded,
              iconColor: const Color(0xFF7038F5),
              iconBackground: const Color(0xFFF3EFFF),
              title: profile.email.trim().isEmpty ? 'Adresse e-mail non renseignée' : profile.email.trim(),
              onTap: _editProfile,
              showChevron: false,
              divider: false,
            ),
          ],
        ),
        const SizedBox(height: 22),
        const _SectionTitle('Préférences'),
        const SizedBox(height: 10),
        _ProfileGroup(
          children: [
            _ProfileRow(
              icon: Icons.tune_rounded,
              iconColor: const Color(0xFF6175AA),
              iconBackground: const Color(0xFFF1F4F9),
              title: 'Aucune préférence configurée pour le moment.',
              onTap: _openNotificationPreferences,
              showChevron: false,
              divider: false,
            ),
          ],
        ),
        const SizedBox(height: 22),
        const _SectionTitle('Sécurité'),
        const SizedBox(height: 10),
        _ProfileGroup(
          children: [
            _ProfileRow(
              icon: Icons.verified_user_outlined,
              iconColor: const Color(0xFF6175AA),
              iconBackground: const Color(0xFFF1F4F9),
              title: 'Configurez votre sécurité après avoir complété votre profil.',
              onTap: _openSecurity,
              showChevron: false,
              divider: false,
            ),
          ],
        ),
        const SizedBox(height: 22),
        _HelpCard(onTap: _openSupport),
      ],
    );
  }

  Widget _buildError() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.person_off_outlined, color: _muted, size: 54),
            const SizedBox(height: 14),
            Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: _muted, height: 1.45)),
            const SizedBox(height: 18),
            OutlinedButton(onPressed: _load, child: const Text('Réessayer')),
          ],
        ),
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
        style: const TextStyle(color: Color(0xFF071B53), fontSize: 16, fontWeight: FontWeight.w900),
      );
}

class _ProfileGroup extends StatelessWidget {
  final List<Widget> children;
  const _ProfileGroup({required this.children});

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(11),
          border: Border.all(color: const Color(0xFFE2E7F0)),
          boxShadow: const [BoxShadow(color: Color(0x07000000), blurRadius: 10, offset: Offset(0, 3))],
        ),
        child: Column(children: children),
      );
}

class _ProfileRow extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final Color iconBackground;
  final String title;
  final String? subtitle;
  final String? trailingText;
  final Color? trailingTextColor;
  final VoidCallback onTap;
  final bool showChevron;
  final bool divider;

  const _ProfileRow({
    required this.icon,
    required this.iconColor,
    required this.iconBackground,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.trailingText,
    this.trailingTextColor,
    this.showChevron = true,
    this.divider = true,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Container(
          constraints: const BoxConstraints(minHeight: 76),
          margin: const EdgeInsets.symmetric(horizontal: 0),
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          decoration: BoxDecoration(
            border: divider ? const Border(bottom: BorderSide(color: Color(0xFFE6EAF1))) : null,
          ),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(color: iconBackground, borderRadius: BorderRadius.circular(10)),
                child: Icon(icon, color: iconColor, size: 25),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(title, style: const TextStyle(color: Color(0xFF071B53), fontSize: 14.2, height: 1.22, fontWeight: FontWeight.w800)),
                    if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(subtitle!, style: const TextStyle(color: Color(0xFF53658F), fontSize: 12.2, height: 1.25)),
                    ],
                  ],
                ),
              ),
              if (trailingText != null) ...[
                const SizedBox(width: 8),
                Text(
                  trailingText!,
                  style: TextStyle(color: trailingTextColor ?? const Color(0xFF53658F), fontSize: 12.2, fontWeight: FontWeight.w500),
                ),
              ],
              if (showChevron) ...[
                const SizedBox(width: 8),
                const Icon(Icons.chevron_right_rounded, color: Color(0xFF071B53), size: 24),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _HelpCard extends StatelessWidget {
  final VoidCallback onTap;
  const _HelpCard({required this.onTap});

  @override
  Widget build(BuildContext context) => Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
        decoration: BoxDecoration(
          color: const Color(0xFFFAFCFF),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFCEDFFF)),
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
              child: const Icon(Icons.contact_support_outlined, color: Color(0xFF1464EB), size: 28),
            ),
            const SizedBox(width: 14),
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Besoin d’aide sur votre compte ?', style: TextStyle(color: Color(0xFF071B53), fontSize: 13.8, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Notre équipe est là pour vous accompagner.', style: TextStyle(color: Color(0xFF53658F), fontSize: 12.0)),
                ],
              ),
            ),
            const SizedBox(width: 10),
            SizedBox(
              height: 40,
              child: FilledButton(
                onPressed: onTap,
                style: FilledButton.styleFrom(
                  backgroundColor: OvanieColors.orange,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 17),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                ),
                child: const Text('Nous contacter', style: TextStyle(fontSize: 12.0, fontWeight: FontWeight.w900)),
              ),
            ),
          ],
        ),
      );
}
