import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../auth/domain/session_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';
import 'delete_account_screen.dart';

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
  bool _saving = false;

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
    try {
      final items = await _repository.sessions();
      if (!mounted) return;
      setState(() {
        _sessions = items;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() => _loading = false);
      _message(ApiClient.friendlyError(error));
    }
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _changePassword() async {
    if (_saving) return;
    if (_current.text.isEmpty || _password.text.length < 8 || _password.text != _confirmation.text) {
      _message('Vérifiez le mot de passe actuel et la confirmation (8 caractères minimum).');
      return;
    }
    setState(() => _saving = true);
    try {
      await _repository.changePassword(
        currentPassword: _current.text,
        password: _password.text,
        confirmation: _confirmation.text,
      );
      _current.clear();
      _password.clear();
      _confirmation.clear();
      _message('Mot de passe modifié.');
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _revoke(ClientDeviceSession session) async {
    try {
      final currentRevoked = await _repository.revokeSession(session.id);
      if (currentRevoked) {
        await SessionStore.instance.clear();
        if (!mounted) return;
        Navigator.of(context).popUntil((route) => route.isFirst);
        _message('Cet appareil a été déconnecté.');
        return;
      }
      _message('Appareil déconnecté.');
      await _loadSessions();
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    }
  }

  String _date(DateTime? value) {
    if (value == null) return 'Jamais utilisé';
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(value.day)}/${two(value.month)}/${value.year} à ${two(value.hour)}:${two(value.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Sécurité & mot de passe')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Modifier le mot de passe', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          TextField(controller: _current, obscureText: true, decoration: const InputDecoration(labelText: 'Mot de passe actuel')),
          const SizedBox(height: 10),
          TextField(controller: _password, obscureText: true, decoration: const InputDecoration(labelText: 'Nouveau mot de passe')),
          const SizedBox(height: 10),
          TextField(controller: _confirmation, obscureText: true, decoration: const InputDecoration(labelText: 'Confirmer le nouveau mot de passe')),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _saving ? null : _changePassword,
            style: FilledButton.styleFrom(backgroundColor: OvanieColors.blue, minimumSize: const Size.fromHeight(46)),
            child: Text(_saving ? 'Modification…' : 'Modifier le mot de passe'),
          ),
          const SizedBox(height: 28),
          const Text('Appareils connectés', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          const Text(
            'La liste combine les sessions Web réellement présentes dans Laravel et les jetons mobiles Sanctum actifs.',
            style: TextStyle(color: OvanieColors.muted, height: 1.4),
          ),
          const SizedBox(height: 12),
          if (_loading)
            const Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator()))
          else if (_sessions.isEmpty)
            const Text('Aucune autre session mobile active.')
          else
            ..._sessions.map(
              (session) => Card(
                margin: const EdgeInsets.only(bottom: 10),
                child: ListTile(
                  leading: Icon(session.kind == 'web' ? Icons.language_rounded : (session.isCurrent ? Icons.smartphone_rounded : Icons.devices_other_rounded), color: OvanieColors.blue),
                  title: Text(session.isCurrent ? '${session.name} • cet appareil' : session.name, style: const TextStyle(fontWeight: FontWeight.w800)),
                  subtitle: Text('Dernière activité : ${_date(session.lastUsedAt ?? session.createdAt)}${session.ipAddress.isEmpty ? '' : '\nIP : ${session.ipAddress}'}'),
                  trailing: TextButton(
                    onPressed: () => _revoke(session),
                    child: Text(session.isCurrent ? 'Déconnecter' : 'Révoquer'),
                  ),
                ),
              ),
            ),
          const SizedBox(height: 30),
          const Divider(),
          const SizedBox(height: 14),
          const Text('Zone sensible', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900, color: OvanieColors.danger)),
          const SizedBox(height: 7),
          const Text('La suppression du compte applique les mêmes contrôles et la même anonymisation que sur le Web OVANIE.', style: TextStyle(color: OvanieColors.muted, height: 1.4)),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => const DeleteAccountScreen())),
            style: OutlinedButton.styleFrom(foregroundColor: OvanieColors.danger, side: const BorderSide(color: OvanieColors.danger), minimumSize: const Size.fromHeight(46)),
            icon: const Icon(Icons.delete_forever_outlined),
            label: const Text('Supprimer mon compte'),
          ),
        ],
      ),
    );
  }
}
