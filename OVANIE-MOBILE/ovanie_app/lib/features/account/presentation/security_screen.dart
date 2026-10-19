import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../auth/domain/session_store.dart';
import '../../payments/presentation/payment_ui.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';
import 'delete_account_screen.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _line = Color(0xFFE7ECF2);
const _pageBackground = Color(0xFFF6F8FB);

class SecurityScreen extends StatefulWidget {
  const SecurityScreen({super.key});

  @override
  State<SecurityScreen> createState() => _SecurityScreenState();
}

class _SecurityScreenState extends State<SecurityScreen> {
  final _repository = const ClientAccountRepository();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  List<ClientDeviceSession> _sessions = const [];
  bool _loading = true;
  String? _sessionsError;
  bool _saving = false;
  String? _passwordError;

  @override
  void initState() {
    super.initState();
    _loadSessions();
  }

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _loadSessions() async {
    setState(() {
      _loading = true;
      _sessionsError = null;
    });
    try {
      final items = [...await _repository.sessions()]
        ..sort((a, b) => a.isCurrent == b.isCurrent ? 0 : (a.isCurrent ? -1 : 1));
      if (!mounted) return;
      setState(() {
        _sessions = items;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _sessionsError = ApiClient.friendlyError(error);
      });
    }
  }

  Future<void> _changePassword() async {
    if (_saving) return;
    FocusScope.of(context).unfocus();
    if (_current.text.isEmpty || _password.text.length < 8 || _password.text != _confirmation.text) {
      setState(() => _passwordError = 'Vérifiez le mot de passe actuel et la confirmation (8 caractères minimum).');
      return;
    }
    setState(() {
      _saving = true;
      _passwordError = null;
    });
    try {
      await _repository.changePassword(
        currentPassword: _current.text,
        password: _password.text,
        confirmation: _confirmation.text,
      );
      _current.clear();
      _password.clear();
      _confirmation.clear();
      if (mounted) showOvanieToast(context, 'Mot de passe modifié.');
    } catch (error) {
      if (mounted) setState(() => _passwordError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _revoke(ClientDeviceSession session) async {
    final confirmed = await showOvanieConfirmDialog(
      context,
      visual: Container(
        width: 84,
        height: 84,
        decoration: const BoxDecoration(color: Color(0xFFFDE4E4), shape: BoxShape.circle),
        child: Icon(_deviceIcon(session), color: OvanieColors.danger, size: 40),
      ),
      title: 'Révoquer cet appareil ?',
      message: '« ${session.name} » sera déconnecté de votre compte OVANIE.',
      confirmLabel: 'Révoquer',
      confirmColor: OvanieColors.danger,
      cancelLabel: 'Annuler',
    );
    if (!confirmed || !mounted) return;
    try {
      final currentRevoked = await _repository.revokeSession(session.id);
      if (currentRevoked) {
        await SessionStore.instance.clear();
        if (!mounted) return;
        Navigator.of(context).popUntil((route) => route.isFirst);
        return;
      }
      if (!mounted) return;
      setState(() => _sessions = _sessions.where((item) => item.id != session.id).toList());
      showOvanieToast(context, 'Appareil déconnecté.');
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    }
  }

  IconData _deviceIcon(ClientDeviceSession session) {
    if (session.kind == 'web') return Icons.laptop_rounded;
    return Icons.smartphone_rounded;
  }

  String _date(DateTime? value) {
    if (value == null) return 'inconnue';
    final local = value.toLocal();
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(local.day)}/${two(local.month)}/${local.year} à ${two(local.hour)}:${two(local.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    final others = _sessions.where((session) => !session.isCurrent).length;
    return Scaffold(
      backgroundColor: _pageBackground,
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
          'Sécurité & mot de passe',
          style: TextStyle(color: _navy, fontSize: 19, fontWeight: FontWeight.w800),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(14, 4, 14, 28),
        children: [
          _Card(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Modifier le mot de passe',
                  style: TextStyle(color: _navy, fontSize: 18, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                _PasswordField(label: 'Mot de passe actuel', controller: _current),
                _PasswordField(label: 'Nouveau mot de passe', controller: _password),
                _PasswordField(label: 'Confirmer le nouveau mot de passe', controller: _confirmation),
                if (_passwordError != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF1F1),
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(color: const Color(0xFFF7C9C9)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.error_outline_rounded, color: OvanieColors.danger, size: 22),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            _passwordError!,
                            style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5, height: 1.35),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                _LoadingButton(
                  label: 'Modifier le mot de passe',
                  loadingLabel: 'Modification…',
                  loading: _saving,
                  onPressed: _changePassword,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _Card(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Appareils connectés',
                  style: TextStyle(color: _navy, fontSize: 18, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Retrouvez les appareils et navigateurs connectés à votre compte.',
                  style: TextStyle(color: _muted, fontSize: 13.5, height: 1.4),
                ),
                const SizedBox(height: 6),
                if (_loading)
                  const Padding(
                    padding: EdgeInsets.all(20),
                    child: Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
                  )
                else if (_sessionsError != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 10),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(_sessionsError!, style: const TextStyle(color: _muted, fontSize: 13.5)),
                        ),
                        TextButton(
                          onPressed: _loadSessions,
                          child: const Text(
                            'Réessayer',
                            style: TextStyle(color: OvanieColors.orange, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ),
                  )
                else ...[
                  for (var i = 0; i < _sessions.length; i++) ...[
                    if (i > 0) const Divider(height: 1, color: _line),
                    _SessionRow(
                      session: _sessions[i],
                      icon: _deviceIcon(_sessions[i]),
                      lastActivity: _date(_sessions[i].lastUsedAt ?? _sessions[i].createdAt),
                      onRevoke: _sessions[i].isCurrent ? null : () => _revoke(_sessions[i]),
                    ),
                  ],
                  if (others == 0) ...[
                    if (_sessions.isNotEmpty) const Divider(height: 1, color: _line),
                    const Padding(
                      padding: EdgeInsets.only(top: 12),
                      child: Text(
                        'Aucune autre session mobile active.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: _muted, fontSize: 13.5),
                      ),
                    ),
                  ],
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
            decoration: BoxDecoration(
              color: const Color(0xFFFFF0F1),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFF5C2C6)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text(
                  'Zone sensible',
                  style: TextStyle(color: OvanieColors.danger, fontSize: 18, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                const Text(
                  'La suppression du compte applique les mêmes contrôles que sur le site OVANIE.',
                  style: TextStyle(color: Color(0xFF5E6676), fontSize: 13.5, height: 1.4),
                ),
                const SizedBox(height: 12),
                SizedBox(
                  height: 44,
                  child: OutlinedButton(
                    onPressed: () => Navigator.of(
                      context,
                    ).push(MaterialPageRoute<void>(builder: (_) => const DeleteAccountScreen())),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: OvanieColors.danger,
                      backgroundColor: Colors.white.withValues(alpha: 0.4),
                      side: const BorderSide(color: OvanieColors.danger, width: 1.3),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                    ),
                    child: const Text(
                      'Supprimer mon compte',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                    ),
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

class _Card extends StatelessWidget {
  const _Card({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      border: Border.all(color: _line),
      boxShadow: const [BoxShadow(color: Color(0x0A102A4C), blurRadius: 8, offset: Offset(0, 2))],
    ),
    child: child,
  );
}

class _PasswordField extends StatefulWidget {
  const _PasswordField({required this.label, required this.controller});

  final String label;
  final TextEditingController controller;

  @override
  State<_PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<_PasswordField> {
  bool _visible = false;

  @override
  Widget build(BuildContext context) {
    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(9),
      borderSide: BorderSide(color: color, width: 1.2),
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(top: 12, bottom: 6),
          child: Text(widget.label, style: const TextStyle(color: _muted, fontSize: 13.5)),
        ),
        TextField(
          controller: widget.controller,
          obscureText: !_visible,
          enableSuggestions: false,
          autocorrect: false,
          style: const TextStyle(color: _navy, fontSize: 15.5),
          decoration: InputDecoration(
            isDense: true,
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            enabledBorder: border(const Color(0xFFD5DCE6)),
            focusedBorder: border(OvanieColors.navy),
            suffixIcon: IconButton(
              onPressed: () => setState(() => _visible = !_visible),
              tooltip: _visible ? 'Masquer' : 'Afficher',
              icon: Icon(
                _visible ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                color: const Color(0xFF4A586E),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _LoadingButton extends StatelessWidget {
  const _LoadingButton({
    required this.label,
    required this.loadingLabel,
    required this.loading,
    required this.onPressed,
  });

  final String label;
  final String loadingLabel;
  final bool loading;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 46,
      child: FilledButton(
        onPressed: loading ? () {} : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: OvanieColors.orange,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (loading) ...[
              const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
              ),
              const SizedBox(width: 12),
            ],
            Text(loading ? loadingLabel : label, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _SessionRow extends StatelessWidget {
  const _SessionRow({required this.session, required this.icon, required this.lastActivity, this.onRevoke});

  final ClientDeviceSession session;
  final IconData icon;
  final String lastActivity;
  final VoidCallback? onRevoke;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          Icon(icon, color: const Color(0xFF2E3A4D), size: 26),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  session.isCurrent ? '${session.name} • cet appareil' : session.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 3),
                Text('Dernière activité : $lastActivity', style: const TextStyle(color: _muted, fontSize: 12.5)),
              ],
            ),
          ),
          if (onRevoke != null)
            TextButton(
              onPressed: onRevoke,
              style: TextButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 6)),
              child: const Text(
                'Révoquer',
                style: TextStyle(color: OvanieColors.danger, fontSize: 14, fontWeight: FontWeight.w700),
              ),
            ),
        ],
      ),
    );
  }
}
