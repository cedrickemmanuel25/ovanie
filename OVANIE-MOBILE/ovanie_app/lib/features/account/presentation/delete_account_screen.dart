import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

class DeleteAccountScreen extends StatefulWidget {
  const DeleteAccountScreen({super.key});
  @override
  State<DeleteAccountScreen> createState() => _DeleteAccountScreenState();
}

class _DeleteAccountScreenState extends State<DeleteAccountScreen> {
  final _repository = const ClientAccountRepository();
  final _word = TextEditingController();
  final _password = TextEditingController();
  final _code = TextEditingController();
  AccountDeletionStatus? _status;
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  @override
  void dispose() { _word.dispose(); _password.dispose(); _code.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final status = await _repository.accountDeletionStatus();
      if (!mounted) return;
      setState(() { _status = status; _loading = false; _error = null; });
    } catch (error) {
      if (!mounted) return;
      setState(() { _loading = false; _error = ApiClient.friendlyError(error); });
    }
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _requestCode() async {
    if (_busy) return;
    setState(() => _busy = true);
    try { _message(await _repository.requestAccountDeletionCode()); }
    catch (e) { _message(ApiClient.friendlyError(e)); }
    finally { if (mounted) setState(() => _busy = false); }
  }

  Future<void> _delete() async {
    final status = _status;
    if (status == null || !status.canDelete || _busy) return;
    if (_word.text.trim() != status.confirmationWord) {
      _message('Saisissez exactement ${status.confirmationWord}.');
      return;
    }
    if (status.codeRequired && _code.text.trim().length != 6) {
      _message('Renseignez le code de confirmation reçu par e-mail.');
      return;
    }
    if (!status.codeRequired && _password.text.isEmpty) {
      _message('Renseignez votre mot de passe actuel.');
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Supprimer définitivement le compte ?'),
        content: const Text('Cette opération utilise le même workflow de suppression/anonymisation que le Web OVANIE. Les obligations légales liées aux commandes restent conservées selon les règles du backend.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Annuler')),
          FilledButton(onPressed: () => Navigator.pop(context, true), style: FilledButton.styleFrom(backgroundColor: OvanieColors.danger), child: const Text('Supprimer mon compte')),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      final message = await _repository.deleteAccount(confirmation: _word.text, password: _password.text, deletionCode: _code.text);
      await SessionStore.instance.clear();
      // Après anonymisation serveur, vider le panier local pour éviter d'afficher
      // des données du compte supprimé sur ce téléphone.
      CartStore.instance.clear();
      if (!mounted) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
      _message(message);
    } catch (error) {
      _message(ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = _status;
    return Scaffold(
      appBar: AppBar(title: const Text('Supprimer mon compte')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Container(
                  padding: const EdgeInsets.all(15),
                  decoration: BoxDecoration(color: OvanieColors.danger.withValues(alpha: .06), borderRadius: BorderRadius.circular(16), border: Border.all(color: OvanieColors.danger.withValues(alpha: .22))),
                  child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [Icon(Icons.warning_amber_rounded, color: OvanieColors.danger), SizedBox(width: 8), Text('Action irréversible', style: TextStyle(color: OvanieColors.danger, fontWeight: FontWeight.w900))]),
                    SizedBox(height: 7),
                    Text('L’application ne supprime jamais directement des données métier. Elle demande à Laravel d’exécuter le même contrôle et la même anonymisation que le Web.', style: TextStyle(height: 1.4)),
                  ]),
                ),
                const SizedBox(height: 18),
                if (_error != null) Text(_error!, style: const TextStyle(color: OvanieColors.danger)),
                if (status != null && !status.canDelete) ...[
                  const Text('Suppression impossible actuellement', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17)),
                  const SizedBox(height: 8),
                  Text(status.reason.isEmpty ? 'Une opération active empêche actuellement la suppression du compte.' : status.reason),
                  const SizedBox(height: 14),
                  OutlinedButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Vérifier à nouveau')),
                ] else if (status != null) ...[
                  Text('Pour confirmer, saisissez ${status.confirmationWord}.', style: const TextStyle(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  TextField(controller: _word, decoration: InputDecoration(labelText: status.confirmationWord)),
                  const SizedBox(height: 12),
                  if (status.codeRequired) ...[
                    Text('Un code à 6 chiffres sera envoyé à ${status.email}.', style: const TextStyle(color: OvanieColors.muted)),
                    const SizedBox(height: 8),
                    OutlinedButton(onPressed: _busy ? null : _requestCode, child: const Text('Envoyer le code de confirmation')),
                    const SizedBox(height: 8),
                    TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6, decoration: const InputDecoration(labelText: 'Code à 6 chiffres')),
                  ] else ...[
                    TextField(controller: _password, obscureText: true, decoration: const InputDecoration(labelText: 'Mot de passe actuel')),
                  ],
                  const SizedBox(height: 18),
                  FilledButton.icon(
                    onPressed: _busy ? null : _delete,
                    style: FilledButton.styleFrom(backgroundColor: OvanieColors.danger, minimumSize: const Size.fromHeight(50)),
                    icon: const Icon(Icons.delete_forever_outlined),
                    label: Text(_busy ? 'Traitement…' : 'Supprimer mon compte', style: const TextStyle(fontWeight: FontWeight.w900)),
                  ),
                ],
              ],
            ),
    );
  }
}
