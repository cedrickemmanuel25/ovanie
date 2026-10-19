import 'package:flutter/material.dart';

import '../../core/ui/mk_dialogs.dart';

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

  const ResetPasswordScreen({super.key, required this.token, required this.email, this.previewDone = false});

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
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.previewDone) WidgetsBinding.instance.addPostFrameCallback((_) => _showDone());
  }

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
      if (mounted) await _showDone();
    } catch (error) {
      if (mounted) setState(() => _error = ApiClient.friendlyError(error));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _goToLogin() {
    Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute<void>(builder: (_) => const LoginScreen()), (_) => false);
  }

  /// Fenêtre de succès : le seul chemin est la connexion avec le nouveau mot de passe.
  Future<void> _showDone() async {
    if (!mounted) return;
    await showMkDialog<void>(
      context,
      dismissible: false,
      header: Padding(
        // Place laissée sous l'image pour la pastille verte qui la chevauche.
        padding: const EdgeInsets.only(bottom: 44),
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.bottomCenter,
          children: [
            ShaderMask(
              blendMode: BlendMode.dstIn,
              shaderCallback: (rect) => const LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.white, Colors.white, Colors.transparent],
                stops: [0, .7, 1],
              ).createShader(rect),
              child: Image.asset(
                'assets/images/mockups/reset_done.jpg',
                height: 190,
                width: double.infinity,
                fit: BoxFit.cover,
                excludeFromSemantics: true,
              ),
            ),
            Positioned(
              bottom: -34,
              child: Container(
                padding: const EdgeInsets.all(5),
                decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                child: const MkIconDisc(Icons.check_rounded, color: Color(0xFF16A34A), size: 68),
              ),
            ),
          ],
        ),
      ),
      title: 'Mot de passe modifié',
      message: 'Votre nouveau mot de passe est maintenant valable sur le Web et dans l’application Vendeur.',
      actions: const [MkDialogAction('Se connecter', null)],
    );
    if (mounted) _goToLogin();
  }

  @override
  Widget build(BuildContext context) {
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
              if (_error != null) ...<Widget>[MkNotice(_error!), const SizedBox(height: 16)],
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
                  suffix: MkEye(hidden: _hidePassword, onTap: _saving ? null : () => setState(() => _hidePassword = !_hidePassword)),
                ),
                validator: (value) => (value ?? '').length < 8 ? 'Le mot de passe doit contenir au moins 8 caractères.' : null,
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
                validator: (value) => value != _password.text ? 'Les deux mots de passe ne sont pas identiques.' : null,
              ),
              const SizedBox(height: 24),
              MkButton(label: _saving ? 'Enregistrement…' : 'Réinitialiser le mot de passe', loading: _saving, onPressed: _submit),
            ],
          ),
        ),
      ],
    );
  }
}
