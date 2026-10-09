import 'package:flutter/material.dart';

import '../../core/network/api_client.dart';
import '../../core/ui/vendor_mockup_ui.dart';
import '../../data/vendor_repository.dart';
import 'login_screen.dart';

class ResetPasswordScreen extends StatefulWidget {
  final String token;
  final String email;

  /// Aperçu de design uniquement : affiche directement l'état « mot de passe modifié ».
  @visibleForTesting
  final bool previewDone;

  const ResetPasswordScreen({
    super.key,
    required this.token,
    required this.email,
    this.previewDone = false,
  });

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _email = TextEditingController(text: widget.email);
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _hidePassword = true;
  bool _hideConfirmation = true;
  bool _saving = false;
  late bool _done = widget.previewDone;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_form.currentState?.validate() ?? false)) return;

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await VendorRepository.instance.resetPassword(
        email: widget.email,
        token: widget.token,
        password: _password.text,
        confirmation: _confirmation.text,
      );
      if (mounted) setState(() => _done = true);
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _goToLogin() {
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_done) {
      return PopScope(
        canPop: false,
        child: MkCardAuthScaffold(
          showBack: false,
          icon: Icons.check_rounded,
          title: 'Mot de passe modifié',
          subtitle: 'Votre nouveau mot de passe est actif sur le site et dans l’application Vendeur.',
          children: <Widget>[
            const MkNotice(
              'Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.',
              success: true,
            ),
            const SizedBox(height: 22),
            MkButton(label: 'Se connecter', onPressed: _goToLogin),
          ],
        ),
      );
    }

    return MkCardAuthScaffold(
      icon: Icons.lock_reset_rounded,
      title: 'Réinitialiser le mot de passe',
      subtitle: 'Choisissez un nouveau mot de passe sécurisé pour votre compte vendeur.',
      children: <Widget>[
        Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (_error != null) ...<Widget>[
                MkNotice(_error!),
                const SizedBox(height: 16),
              ],
              const MkLabel('Adresse e-mail'),
              TextFormField(
                controller: _email,
                readOnly: true,
                style: mkFieldText,
                decoration: mkInput(hint: '', icon: Icons.mail_outline_rounded, readOnly: true),
              ),
              const SizedBox(height: 18),
              const MkLabel('Nouveau mot de passe'),
              TextFormField(
                controller: _password,
                obscureText: _hidePassword,
                enabled: !_saving,
                style: mkFieldText,
                textInputAction: TextInputAction.next,
                autovalidateMode: AutovalidateMode.onUserInteraction,
                autofillHints: const <String>[AutofillHints.newPassword],
                onChanged: (_) => setState(() {}),
                decoration: mkInput(
                  hint: 'Au moins 8 caractères',
                  icon: Icons.lock_outline_rounded,
                  suffix: MkEye(
                    hidden: _hidePassword,
                    onTap: _saving ? null : () => setState(() => _hidePassword = !_hidePassword),
                  ),
                ),
                validator: (value) =>
                    (value ?? '').length < 8 ? 'Le mot de passe doit contenir au moins 8 caractères.' : null,
              ),
              const SizedBox(height: 18),
              const MkLabel('Confirmer le mot de passe'),
              TextFormField(
                controller: _confirmation,
                obscureText: _hideConfirmation,
                enabled: !_saving,
                style: mkFieldText,
                textInputAction: TextInputAction.done,
                autovalidateMode: AutovalidateMode.onUserInteraction,
                autofillHints: const <String>[AutofillHints.newPassword],
                onChanged: (_) => setState(() {}),
                onFieldSubmitted: (_) => _submit(),
                decoration: mkInput(
                  hint: 'Saisissez-le une seconde fois',
                  icon: Icons.lock_outline_rounded,
                  suffix: MkEye(
                    hidden: _hideConfirmation,
                    onTap: _saving ? null : () => setState(() => _hideConfirmation = !_hideConfirmation),
                  ),
                ),
                validator: (value) =>
                    value != _password.text ? 'Les deux mots de passe ne sont pas identiques.' : null,
              ),
              const SizedBox(height: 24),
              MkButton(
                label: _saving ? 'Enregistrement…' : 'Réinitialiser le mot de passe',
                loading: _saving,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
