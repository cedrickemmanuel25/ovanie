import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../app/theme.dart';
import '../../../core/network/api_client.dart';
import '../../auth/domain/session_store.dart';
import '../../cart/domain/cart_store.dart';
import '../../payments/presentation/payment_ui.dart';
import '../data/client_account_repository.dart';
import '../domain/account_models.dart';

const _navy = Color(0xFF0A1B3D);
const _muted = Color(0xFF6B7890);
const _fieldBorder = Color(0xFFD5DCE6);
const _deleteRed = Color(0xFFC81E1E);

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
  bool _checking = false;
  bool _sendingCode = false;
  bool _passwordVisible = false;
  String? _loadError;
  String? _wordError;
  String? _codeError;
  String? _passwordError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _word.dispose();
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _checking = true;
      _loadError = null;
    });
    try {
      final status = await _repository.accountDeletionStatus();
      if (!mounted) return;
      setState(() => _status = status);
    } catch (error) {
      if (mounted) setState(() => _loadError = ApiClient.friendlyError(error));
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
          _checking = false;
        });
      }
    }
  }

  Future<void> _requestCode() async {
    if (_sendingCode) return;
    setState(() => _sendingCode = true);
    try {
      final message = await _repository.requestAccountDeletionCode();
      if (mounted) showOvanieToast(context, message);
    } catch (error) {
      if (mounted) showOvanieToast(context, ApiClient.friendlyError(error), error: true);
    } finally {
      if (mounted) setState(() => _sendingCode = false);
    }
  }

  bool _validate(AccountDeletionStatus status) {
    setState(() {
      _wordError = _word.text.trim() == status.confirmationWord
          ? null
          : 'Saisissez exactement ${status.confirmationWord}.';
      _codeError = status.codeRequired && _code.text.trim().length != 6
          ? 'Renseignez le code de confirmation reçu par e-mail.'
          : null;
      _passwordError = !status.codeRequired && _password.text.isEmpty ? 'Renseignez votre mot de passe actuel.' : null;
    });
    return _wordError == null && _codeError == null && _passwordError == null;
  }

  Future<void> _delete() async {
    final status = _status;
    if (status == null || !status.canDelete) return;
    FocusScope.of(context).unfocus();
    if (!_validate(status)) return;

    final message = await showDialog<String>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _FinalConfirmDialog(
        onConfirm: () => _repository.deleteAccount(
          confirmation: _word.text.trim(),
          password: _password.text,
          deletionCode: _code.text.trim(),
        ),
      ),
    );
    if (message == null || !mounted) return;
    await SessionStore.instance.clear();
    // Après l'anonymisation côté serveur, on vide le panier local pour ne plus
    // afficher de données du compte supprimé sur ce téléphone.
    CartStore.instance.clear();
    if (!mounted) return;
    // Le toast est porté par le ScaffoldMessenger racine : il reste visible
    // après le retour à l'accueil du compte.
    showOvanieToast(context, message);
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    final status = _status;
    final blocked = status != null && !status.canDelete;
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        leading: IconButton(
          onPressed: () => Navigator.maybePop(context),
          tooltip: 'Retour',
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _navy, size: 22),
        ),
        title: const Text(
          'Supprimer mon compte',
          style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: OvanieColors.orange))
          : status == null
          ? _LoadError(message: _loadError ?? 'Vérification indisponible.', onRetry: _load)
          : ListView(
              padding: const EdgeInsets.fromLTRB(14, 6, 14, 28),
              children: [
                if (blocked)
                  _BlockedBanner(reason: status.reason, checking: _checking, onRetry: _load)
                else
                  const _IrreversibleBanner(),
                const SizedBox(height: 22),
                _Label('Pour confirmer, saisissez ${status.confirmationWord}.'),
                _Input(
                  controller: _word,
                  hint: status.confirmationWord,
                  enabled: !blocked,
                  hasError: _wordError != null,
                  capitalization: TextCapitalization.characters,
                  onChanged: (_) {
                    if (_wordError != null) setState(() => _wordError = null);
                  },
                ),
                _ErrorLine(_wordError),
                if (status.codeRequired) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                    decoration: BoxDecoration(color: const Color(0xFFF0F3F7), borderRadius: BorderRadius.circular(10)),
                    child: Row(
                      children: [
                        const Icon(Icons.info_outline_rounded, color: Color(0xFF3B4A60), size: 22),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Text.rich(
                            TextSpan(
                              text: 'Un code à 6 chiffres sera envoyé à\n',
                              style: const TextStyle(color: Color(0xFF5E6B80)),
                              children: [
                                TextSpan(
                                  text: '${status.email}.',
                                  style: const TextStyle(color: _navy, fontWeight: FontWeight.w600),
                                ),
                              ],
                            ),
                            style: const TextStyle(fontSize: 14, height: 1.45),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    height: 46,
                    child: OutlinedButton(
                      onPressed: blocked || _sendingCode ? null : _requestCode,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _navy,
                        disabledForegroundColor: const Color(0xFF9AA5B5),
                        backgroundColor: blocked ? const Color(0xFFF2F4F7) : null,
                        side: BorderSide(color: blocked ? _fieldBorder : _navy, width: 1.4),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                      ),
                      child: _sendingCode
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2.2, color: _navy),
                            )
                          : const Text(
                              'Envoyer le code de confirmation',
                              style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
                            ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  const _Label('Code à 6 chiffres'),
                  _CodeBoxes(
                    controller: _code,
                    enabled: !blocked,
                    hasError: _codeError != null,
                    onChanged: () {
                      if (_codeError != null) setState(() => _codeError = null);
                    },
                  ),
                  _ErrorLine(_codeError),
                ] else ...[
                  const SizedBox(height: 18),
                  const _Label('Mot de passe actuel'),
                  _Input(
                    controller: _password,
                    enabled: !blocked,
                    obscure: !_passwordVisible,
                    hasError: _passwordError != null,
                    onChanged: (_) {
                      if (_passwordError != null) setState(() => _passwordError = null);
                    },
                    suffix: IconButton(
                      onPressed: () => setState(() => _passwordVisible = !_passwordVisible),
                      tooltip: _passwordVisible ? 'Masquer' : 'Afficher',
                      icon: Icon(
                        _passwordVisible ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                        color: const Color(0xFF4A586E),
                      ),
                    ),
                  ),
                  _ErrorLine(_passwordError),
                ],
                const SizedBox(height: 26),
                SizedBox(
                  height: 50,
                  child: FilledButton(
                    onPressed: blocked ? null : _delete,
                    style: FilledButton.styleFrom(
                      backgroundColor: _deleteRed,
                      disabledBackgroundColor: const Color(0xFFD9767C),
                      foregroundColor: Colors.white,
                      disabledForegroundColor: Colors.white.withValues(alpha: 0.85),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    ),
                    child: const Text(
                      'Supprimer mon compte',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}

class _IrreversibleBanner extends StatelessWidget {
  const _IrreversibleBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF0F1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF5C2C6)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.warning_amber_rounded, color: OvanieColors.danger, size: 32),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Action irréversible',
                  style: TextStyle(color: OvanieColors.danger, fontSize: 17, fontWeight: FontWeight.w800),
                ),
                SizedBox(height: 4),
                Text(
                  'Votre compte sera supprimé selon les règles OVANIE. Les informations liées à vos commandes sont '
                  'conservées lorsque la loi l’exige.',
                  style: TextStyle(color: Color(0xFF5E6676), fontSize: 13.5, height: 1.45),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _BlockedBanner extends StatelessWidget {
  const _BlockedBanner({required this.reason, required this.checking, required this.onRetry});

  final String reason;
  final bool checking;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    const brown = Color(0xFF9A4A0C);
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF4E8),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF4C98E)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: const BoxDecoration(color: Color(0xFFFCE3C8), shape: BoxShape.circle),
                child: const Icon(Icons.person_outline_rounded, color: brown, size: 26),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Suppression impossible actuellement',
                      style: TextStyle(color: brown, fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      reason.trim().isEmpty
                          ? 'Une opération active empêche actuellement la suppression du compte.'
                          : reason,
                      style: const TextStyle(color: Color(0xFF5E6676), fontSize: 13.5, height: 1.4),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 44,
            child: OutlinedButton(
              onPressed: checking ? null : onRetry,
              style: OutlinedButton.styleFrom(
                foregroundColor: brown,
                side: const BorderSide(color: Color(0xFFD9822B), width: 1.4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
              ),
              child: checking
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2.2, color: brown),
                    )
                  : const Text('Vérifier à nouveau', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
            ),
          ),
        ],
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text(
      text,
      style: const TextStyle(color: _navy, fontSize: 14.5, fontWeight: FontWeight.w700),
    ),
  );
}

class _ErrorLine extends StatelessWidget {
  const _ErrorLine(this.message);

  final String? message;

  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 7),
      child: Row(
        children: [
          const Icon(Icons.error_outline_rounded, color: OvanieColors.danger, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message!, style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5)),
          ),
        ],
      ),
    );
  }
}

class _Input extends StatelessWidget {
  const _Input({
    required this.controller,
    required this.enabled,
    required this.hasError,
    required this.onChanged,
    this.hint,
    this.obscure = false,
    this.suffix,
    this.capitalization = TextCapitalization.none,
  });

  final TextEditingController controller;
  final bool enabled;
  final bool hasError;
  final ValueChanged<String> onChanged;
  final String? hint;
  final bool obscure;
  final Widget? suffix;
  final TextCapitalization capitalization;

  @override
  Widget build(BuildContext context) {
    OutlineInputBorder border(Color color) => OutlineInputBorder(
      borderRadius: BorderRadius.circular(9),
      borderSide: BorderSide(color: color, width: 1.2),
    );
    return TextField(
      controller: controller,
      enabled: enabled,
      obscureText: obscure,
      autocorrect: false,
      enableSuggestions: !obscure,
      textCapitalization: capitalization,
      onChanged: onChanged,
      style: const TextStyle(color: _navy, fontSize: 15.5),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: Color(0xFFA0AABA)),
        isDense: true,
        filled: true,
        fillColor: enabled ? Colors.white : const Color(0xFFF2F4F7),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),
        enabledBorder: border(hasError ? OvanieColors.danger : _fieldBorder),
        disabledBorder: border(_fieldBorder),
        focusedBorder: border(hasError ? OvanieColors.danger : _navy),
        suffixIcon: suffix,
      ),
    );
  }
}

/// Six cases de code : un champ invisible porte la saisie, les cases l'affichent.
class _CodeBoxes extends StatefulWidget {
  const _CodeBoxes({required this.controller, required this.enabled, required this.hasError, required this.onChanged});

  final TextEditingController controller;
  final bool enabled;
  final bool hasError;
  final VoidCallback onChanged;

  @override
  State<_CodeBoxes> createState() => _CodeBoxesState();
}

class _CodeBoxesState extends State<_CodeBoxes> {
  final _focus = FocusNode();

  @override
  void initState() {
    super.initState();
    _focus.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _focus.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final code = widget.controller.text;
    return GestureDetector(
      onTap: widget.enabled ? () => _focus.requestFocus() : null,
      child: Stack(
        children: [
          Opacity(
            opacity: 0,
            child: SizedBox(
              height: 56,
              child: TextField(
                controller: widget.controller,
                focusNode: _focus,
                enabled: widget.enabled,
                keyboardType: TextInputType.number,
                autofillHints: const [AutofillHints.oneTimeCode],
                inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)],
                onChanged: (_) {
                  setState(() {});
                  widget.onChanged();
                },
              ),
            ),
          ),
          Row(
            children: [
              for (var i = 0; i < 6; i++) ...[
                if (i > 0) const SizedBox(width: 8),
                Expanded(
                  child: Container(
                    height: 56,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: widget.enabled ? Colors.white : const Color(0xFFF2F4F7),
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(
                        color: widget.hasError
                            ? OvanieColors.danger
                            : (_focus.hasFocus && i == code.length.clamp(0, 5) ? _navy : _fieldBorder),
                        width: 1.2,
                      ),
                    ),
                    child: i < code.length
                        ? Text(
                            code[i],
                            style: const TextStyle(color: _navy, fontSize: 22, fontWeight: FontWeight.w700),
                          )
                        : Container(width: 14, height: 1.6, color: const Color(0xFFB5BECB)),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

/// Confirmation finale : la suppression s'exécute depuis la fenêtre, qui
/// affiche « Traitement… » et renvoie le message du serveur en cas de succès.
class _FinalConfirmDialog extends StatefulWidget {
  const _FinalConfirmDialog({required this.onConfirm});

  final Future<String> Function() onConfirm;

  @override
  State<_FinalConfirmDialog> createState() => _FinalConfirmDialogState();
}

class _FinalConfirmDialogState extends State<_FinalConfirmDialog> {
  bool _busy = false;
  String? _error;

  Future<void> _run() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final message = await widget.onConfirm();
      if (mounted) Navigator.of(context).pop(message.trim().isEmpty ? 'Votre compte a été supprimé.' : message);
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = ApiClient.friendlyError(error);
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !_busy,
      child: Dialog(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.white,
        insetPadding: const EdgeInsets.symmetric(horizontal: 36),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 24, 16, 10),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Supprimer définitivement le compte ?',
                textAlign: TextAlign.center,
                style: TextStyle(color: _navy, fontSize: 20, fontWeight: FontWeight.w800, height: 1.25),
              ),
              const SizedBox(height: 10),
              const Text(
                'Cette action est définitive. Les informations liées à vos commandes restent conservées selon les '
                'obligations légales.',
                textAlign: TextAlign.center,
                style: TextStyle(color: _muted, fontSize: 14, height: 1.45),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: OvanieColors.danger, fontSize: 13.5),
                ),
              ],
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                height: 46,
                child: FilledButton(
                  onPressed: _busy ? () {} : _run,
                  style: FilledButton.styleFrom(
                    backgroundColor: _deleteRed,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (_busy) ...[
                        const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
                        ),
                        const SizedBox(width: 12),
                      ],
                      Text(
                        _busy ? 'Traitement…' : 'Supprimer mon compte',
                        style: const TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 4),
              TextButton(
                onPressed: _busy ? null : () => Navigator.of(context).pop(),
                child: const Text('Annuler', style: TextStyle(color: Color(0xFF4A586E), fontSize: 15)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

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
