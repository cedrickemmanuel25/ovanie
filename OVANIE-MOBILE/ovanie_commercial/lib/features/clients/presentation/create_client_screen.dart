import 'package:flutter/material.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/navigation/commercial_tab_bus.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/widgets/brand_background.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../../shops/data/shops_service.dart';
import '../data/clients_service.dart';
import '../models/client_data.dart';
import 'client_chrome.dart';

class CommercialCreateClientScreen extends StatefulWidget {
  const CommercialCreateClientScreen({
    super.key,
    required this.clientsService,
    required this.shopsService,
    required this.initialUser,
    required this.unreadNotifications,
    required this.onLogout,
  });

  final ClientsService clientsService;
  final ShopsService shopsService;
  final Map<String, dynamic> initialUser;
  final int unreadNotifications;
  final Future<void> Function() onLogout;

  @override
  State<CommercialCreateClientScreen> createState() => _CommercialCreateClientScreenState();
}

class _CommercialCreateClientScreenState extends State<CommercialCreateClientScreen> {
  final _searchController = TextEditingController();
  final _fullNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _cityController = TextEditingController(text: 'Abidjan');
  final _communeController = TextEditingController();
  final _notesController = TextEditingController();
  final _passwordController = TextEditingController();
  final _passwordConfirmationController = TextEditingController();

  int _step = 1;
  String _clientType = 'particulier';
  String _phoneCountry = '+225';
  bool _sendCredentials = true;
  bool _passwordVisible = false;
  bool _confirmationVisible = false;
  bool _submitting = false;
  String? _error;

  CommercialProfile get _profile => CommercialProfile.fromJson(widget.initialUser);

  @override
  void dispose() {
    _searchController.dispose();
    _fullNameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _cityController.dispose();
    _communeController.dispose();
    _notesController.dispose();
    _passwordController.dispose();
    _passwordConfirmationController.dispose();
    super.dispose();
  }

  void _futureModule(String title) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$title : cet écran sera relié dans le prochain lot.'),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  Future<void> _showProfileMenu() async {
    final action = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: const Color(0xFF071E43),
      showDragHandle: true,
      builder: (context) => SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _profile.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              const Text('Espace Commercial OVANIE', style: TextStyle(color: Color(0xFF9FB0CF))),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => Navigator.pop(context, 'logout'),
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Se déconnecter'),
              ),
            ],
          ),
        ),
      ),
    );
    if (action == 'logout') {
      if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
      await widget.onLogout();
    }
  }

  void _handleBottomNavigation(int index) {
    if (index == 1) return;
    CommercialTabBus.request(index);
  }

  bool _validateStepOne() {
    final fullName = _fullNameController.text.trim();
    final email = _emailController.text.trim();
    final phoneDigits = _phoneController.text.replaceAll(RegExp(r'\D'), '');

    String? message;
    if (fullName.length < 3) {
      message = 'Saisissez le nom complet du client.';
    } else if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      message = 'Saisissez une adresse e-mail valide.';
    } else if (phoneDigits.length < 8) {
      message = 'Saisissez un numéro de téléphone valide.';
    } else if (_cityController.text.trim().isEmpty) {
      message = 'Saisissez la ville du client.';
    }

    setState(() => _error = message);
    return message == null;
  }

  void _continue() {
    if (!_validateStepOne()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _step = 2;
      _error = null;
    });
  }

  Future<void> _createClient() async {
    final password = _passwordController.text;
    final confirmation = _passwordConfirmationController.text;
    String? message;

    if (!RegExp(r'^(?=.*[A-Za-z])(?=.*\d).{8,}$').hasMatch(password)) {
      message = 'Le mot de passe doit contenir au moins 8 caractères, avec des lettres et des chiffres.';
    } else if (password != confirmation) {
      message = 'La confirmation du mot de passe ne correspond pas.';
    }

    if (message != null) {
      setState(() => _error = message);
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final result = await widget.clientsService.create(
        CreateCommercialClientPayload(
          clientType: _clientType,
          fullName: _fullNameController.text,
          email: _emailController.text,
          phoneCountry: _phoneCountry,
          phone: _phoneController.text,
          city: _cityController.text,
          communeQuartier: _communeController.text,
          notes: _notesController.text,
          password: password,
          passwordConfirmation: confirmation,
          sendCredentials: _sendCredentials,
        ),
      );
      if (!mounted) return;
      Navigator.of(context).pop(result);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _error = 'Impossible de créer le client pour le moment.');
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.78, 1.08).toDouble();
    double s(double value) => value * scale;

    return Scaffold(
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: CustomScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            slivers: [
              SliverPadding(
                padding: EdgeInsets.fromLTRB(s(16), s(9), s(16), s(98)),
                sliver: SliverList(
                  delegate: SliverChildListDelegate.fixed([
                    CommercialClientsHeader(
                      profile: _profile,
                      unreadNotifications: widget.unreadNotifications,
                      scale: scale,
                      onNotificationsTap: () => _futureModule('Notifications'),
                      onAvatarTap: _showProfileMenu,
                    ),
                    SizedBox(height: s(14)),
                    CommercialClientSearch(
                      controller: _searchController,
                      scale: scale,
                      onChanged: (_) => setState(() {}),
                      onFilterTap: () => _futureModule('Filtres clients'),
                    ),
                    SizedBox(height: s(_step == 2 ? 13 : 18)),
                    if (_step == 2) ...[
                      InkWell(
                        onTap: () => setState(() {
                          _step = 1;
                          _error = null;
                        }),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.arrow_back_rounded, color: const Color(0xFFD4DDED), size: s(18)),
                            SizedBox(width: s(7)),
                            Text(
                              'Retour',
                              style: TextStyle(color: const Color(0xFFD4DDED), fontSize: s(11.5)),
                            ),
                          ],
                        ),
                      ),
                      SizedBox(height: s(11)),
                    ],
                    Text(
                      'Créer un client',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: s(23),
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.45,
                      ),
                    ),
                    SizedBox(height: s(5)),
                    Text(
                      'Enregistrez un nouveau client dans votre portefeuille commercial',
                      style: TextStyle(color: const Color(0xFFC4CDDD), fontSize: s(11.1)),
                    ),
                    SizedBox(height: s(14)),
                    Container(
                      padding: EdgeInsets.fromLTRB(s(14), s(15), s(14), s(14)),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFBFBFC),
                        borderRadius: BorderRadius.circular(s(14)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(.10),
                            blurRadius: 14,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          _StepIndicator(step: _step, scale: scale),
                          SizedBox(height: s(16)),
                          if (_error != null) ...[
                            _FormError(message: _error!, scale: scale),
                            SizedBox(height: s(12)),
                          ],
                          if (_step == 1)
                            _StepOneForm(
                              scale: scale,
                              clientType: _clientType,
                              onClientTypeChanged: (value) => setState(() => _clientType = value),
                              fullNameController: _fullNameController,
                              emailController: _emailController,
                              phoneController: _phoneController,
                              phoneCountry: _phoneCountry,
                              onPhoneCountryChanged: (value) => setState(() => _phoneCountry = value),
                              cityController: _cityController,
                              communeController: _communeController,
                              notesController: _notesController,
                              onCancel: () => Navigator.of(context).pop(),
                              onContinue: _continue,
                            )
                          else
                            _StepTwoForm(
                              scale: scale,
                              emailController: _emailController,
                              passwordController: _passwordController,
                              passwordConfirmationController: _passwordConfirmationController,
                              passwordVisible: _passwordVisible,
                              confirmationVisible: _confirmationVisible,
                              onTogglePassword: () => setState(() => _passwordVisible = !_passwordVisible),
                              onToggleConfirmation: () => setState(() => _confirmationVisible = !_confirmationVisible),
                              sendCredentials: _sendCredentials,
                              onSendCredentialsChanged: (value) => setState(() => _sendCredentials = value),
                              submitting: _submitting,
                              onPrevious: () => setState(() {
                                _step = 1;
                                _error = null;
                              }),
                              onCreate: _createClient,
                            ),
                        ],
                      ),
                    ),
                    if (_step == 1) ...[
                      SizedBox(height: s(10)),
                      Container(
                        padding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(9)),
                        decoration: BoxDecoration(
                          color: const Color(0xFF0B2852).withOpacity(.92),
                          borderRadius: BorderRadius.circular(s(11)),
                          border: Border.all(color: const Color(0xFF294E7A), width: .8),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: s(20),
                              height: s(20),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(color: const Color(0xFF2589FF)),
                              ),
                              child: Icon(Icons.info_outline_rounded, color: const Color(0xFF2589FF), size: s(14)),
                            ),
                            SizedBox(width: s(9)),
                            Expanded(
                              child: Text(
                                'Le client recevra ses accès après validation du commercial.',
                                style: TextStyle(color: const Color(0xFFE0E7F3), fontSize: s(9.8)),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: CommercialBottomNavigation(
        scale: scale,
        currentIndex: 1,
        onTap: _handleBottomNavigation,
      ),
    );
  }
}

class _StepIndicator extends StatelessWidget {
  const _StepIndicator({required this.step, required this.scale});

  final int step;
  final double scale;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        _StepBubble(
          scale: scale,
          value: step == 2 ? '✓' : '1',
          active: true,
        ),
        SizedBox(width: s(8)),
        Text(
          'Informations client',
          style: TextStyle(
            color: const Color(0xFF0969F1),
            fontSize: s(11.2),
            fontWeight: FontWeight.w700,
          ),
        ),
        Container(
          width: s(54),
          height: 1,
          margin: EdgeInsets.symmetric(horizontal: s(12)),
          color: step == 2 ? const Color(0xFF257FF1) : const Color(0xFFD3D9E4),
        ),
        _StepBubble(scale: scale, value: '2', active: step == 2),
        SizedBox(width: s(8)),
        Text(
          'Accès au compte',
          style: TextStyle(
            color: step == 2 ? const Color(0xFF0969F1) : const Color(0xFF68758E),
            fontSize: s(11.2),
            fontWeight: step == 2 ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ],
    );
  }
}

class _StepBubble extends StatelessWidget {
  const _StepBubble({required this.scale, required this.value, required this.active});

  final double scale;
  final String value;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 26 * scale,
      height: 26 * scale,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: active ? const Color(0xFF1374F6) : const Color(0xFFE8ECF2),
      ),
      alignment: Alignment.center,
      child: Text(
        value,
        style: TextStyle(
          color: active ? Colors.white : const Color(0xFF4B5870),
          fontSize: 11 * scale,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _StepOneForm extends StatelessWidget {
  const _StepOneForm({
    required this.scale,
    required this.clientType,
    required this.onClientTypeChanged,
    required this.fullNameController,
    required this.emailController,
    required this.phoneController,
    required this.phoneCountry,
    required this.onPhoneCountryChanged,
    required this.cityController,
    required this.communeController,
    required this.notesController,
    required this.onCancel,
    required this.onContinue,
  });

  final double scale;
  final String clientType;
  final ValueChanged<String> onClientTypeChanged;
  final TextEditingController fullNameController;
  final TextEditingController emailController;
  final TextEditingController phoneController;
  final String phoneCountry;
  final ValueChanged<String> onPhoneCountryChanged;
  final TextEditingController cityController;
  final TextEditingController communeController;
  final TextEditingController notesController;
  final VoidCallback onCancel;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Label(text: 'Type de client', scale: scale),
        SizedBox(height: s(7)),
        Row(
          children: [
            Expanded(
              child: _TypeButton(
                scale: scale,
                selected: clientType == 'particulier',
                icon: Icons.person_rounded,
                label: 'Particulier',
                onTap: () => onClientTypeChanged('particulier'),
              ),
            ),
            SizedBox(width: s(8)),
            Expanded(
              child: _TypeButton(
                scale: scale,
                selected: clientType == 'entreprise',
                icon: Icons.apartment_rounded,
                label: 'Entreprise',
                onTap: () => onClientTypeChanged('entreprise'),
              ),
            ),
          ],
        ),
        SizedBox(height: s(12)),
        _Label(text: 'Nom complet', scale: scale),
        SizedBox(height: s(6)),
        _Input(controller: fullNameController, hint: 'Ex: Kouassi Thierry', scale: scale, textCapitalization: TextCapitalization.words),
        SizedBox(height: s(11)),
        _Label(text: 'E-mail', scale: scale),
        SizedBox(height: s(6)),
        _Input(controller: emailController, hint: 'client@email.com', scale: scale, keyboardType: TextInputType.emailAddress),
        SizedBox(height: s(11)),
        _Label(text: 'Téléphone', scale: scale),
        SizedBox(height: s(6)),
        Row(
          children: [
            SizedBox(
              width: s(112),
              child: _PhoneCountry(
                scale: scale,
                value: phoneCountry,
                onChanged: onPhoneCountryChanged,
              ),
            ),
            SizedBox(width: s(8)),
            Expanded(
              child: _Input(
                controller: phoneController,
                hint: '07 08 12 34 56',
                scale: scale,
                keyboardType: TextInputType.phone,
              ),
            ),
          ],
        ),
        SizedBox(height: s(11)),
        _Label(text: 'Ville', scale: scale),
        SizedBox(height: s(6)),
        _Input(controller: cityController, hint: 'Abidjan', scale: scale, textCapitalization: TextCapitalization.words),
        SizedBox(height: s(12)),
        Row(
          children: [
            Expanded(
              child: _OutlineAction(
                scale: scale,
                label: 'Annuler',
                onPressed: onCancel,
              ),
            ),
            SizedBox(width: s(9)),
            Expanded(
              child: _OrangeAction(
                scale: scale,
                label: 'Continuer',
                icon: Icons.arrow_forward_rounded,
                onPressed: onContinue,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StepTwoForm extends StatelessWidget {
  const _StepTwoForm({
    required this.scale,
    required this.emailController,
    required this.passwordController,
    required this.passwordConfirmationController,
    required this.passwordVisible,
    required this.confirmationVisible,
    required this.onTogglePassword,
    required this.onToggleConfirmation,
    required this.sendCredentials,
    required this.onSendCredentialsChanged,
    required this.submitting,
    required this.onPrevious,
    required this.onCreate,
  });

  final double scale;
  final TextEditingController emailController;
  final TextEditingController passwordController;
  final TextEditingController passwordConfirmationController;
  final bool passwordVisible;
  final bool confirmationVisible;
  final VoidCallback onTogglePassword;
  final VoidCallback onToggleConfirmation;
  final bool sendCredentials;
  final ValueChanged<bool> onSendCredentialsChanged;
  final bool submitting;
  final VoidCallback onPrevious;
  final VoidCallback onCreate;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return Container(
      padding: EdgeInsets.fromLTRB(s(12), s(12), s(12), s(12)),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(s(12)),
        boxShadow: [
          BoxShadow(color: const Color(0xFF173456).withOpacity(.07), blurRadius: 12),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                width: s(47),
                height: s(47),
                decoration: const BoxDecoration(shape: BoxShape.circle, color: Color(0xFFEAF4FF)),
                child: Icon(Icons.person_rounded, color: const Color(0xFF086FEF), size: s(27)),
              ),
              SizedBox(width: s(10)),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Accès au compte',
                      style: TextStyle(color: OvanieColors.ink, fontSize: s(14.4), fontWeight: FontWeight.w800),
                    ),
                    SizedBox(height: s(2)),
                    Text(
                      'Définissez les informations de connexion du client',
                      style: TextStyle(color: const Color(0xFF66758F), fontSize: s(9.5)),
                    ),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: s(11)),
          Container(
            padding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(10)),
            decoration: BoxDecoration(
              color: const Color(0xFFEAF4FF),
              borderRadius: BorderRadius.circular(s(10)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.info_outline_rounded, color: const Color(0xFF086FEF), size: s(20)),
                SizedBox(width: s(10)),
                Expanded(
                  child: Text(
                    'Le client pourra se connecter à l’application et au site OVANIE avec ces informations pour passer ses commandes et suivre ses achats.',
                    style: TextStyle(color: const Color(0xFF2B4D7E), fontSize: s(8.9), height: 1.4),
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: s(12)),
          _Label(text: 'Adresse e-mail', scale: scale),
          SizedBox(height: s(6)),
          _Input(
            controller: emailController,
            hint: 'Ex: client@email.com',
            scale: scale,
            keyboardType: TextInputType.emailAddress,
            prefixIcon: Icons.mail_outline_rounded,
          ),
          SizedBox(height: s(11)),
          _Label(text: 'Mot de passe', scale: scale),
          SizedBox(height: s(6)),
          _Input(
            controller: passwordController,
            hint: 'Créer un mot de passe sécurisé',
            scale: scale,
            obscureText: !passwordVisible,
            prefixIcon: Icons.lock_outline_rounded,
            suffixIcon: passwordVisible ? Icons.visibility_outlined : Icons.visibility_off_outlined,
            onSuffixTap: onTogglePassword,
          ),
          SizedBox(height: s(5)),
          Text(
            'Le mot de passe doit contenir au moins 8 caractères, avec des lettres et des chiffres.',
            style: TextStyle(color: const Color(0xFF566781), fontSize: s(8.2)),
          ),
          SizedBox(height: s(10)),
          _Label(text: 'Confirmer le mot de passe', scale: scale),
          SizedBox(height: s(6)),
          _Input(
            controller: passwordConfirmationController,
            hint: 'Répéter le mot de passe',
            scale: scale,
            obscureText: !confirmationVisible,
            prefixIcon: Icons.lock_outline_rounded,
            suffixIcon: confirmationVisible ? Icons.visibility_outlined : Icons.visibility_off_outlined,
            onSuffixTap: onToggleConfirmation,
          ),
          SizedBox(height: s(12)),
          InkWell(
            onTap: () => onSendCredentialsChanged(!sendCredentials),
            borderRadius: BorderRadius.circular(s(10)),
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(12)),
              decoration: BoxDecoration(
                color: const Color(0xFFEAF4FF),
                borderRadius: BorderRadius.circular(s(10)),
              ),
              child: Row(
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 160),
                    width: s(24),
                    height: s(24),
                    decoration: BoxDecoration(
                      color: sendCredentials ? const Color(0xFF1178F5) : Colors.white,
                      borderRadius: BorderRadius.circular(s(4)),
                      border: Border.all(
                        color: sendCredentials ? const Color(0xFF1178F5) : const Color(0xFF9BAAC0),
                      ),
                    ),
                    child: sendCredentials
                        ? Icon(Icons.check_rounded, color: Colors.white, size: s(18))
                        : null,
                  ),
                  SizedBox(width: s(10)),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Envoyer les informations de connexion au client',
                          style: TextStyle(color: OvanieColors.ink, fontSize: s(10.2), fontWeight: FontWeight.w700),
                        ),
                        SizedBox(height: s(3)),
                        Text(
                          'Le client recevra un SMS et un e-mail avec ses identifiants.',
                          style: TextStyle(color: const Color(0xFF5D7192), fontSize: s(8.4)),
                        ),
                      ],
                    ),
                  ),
                  SizedBox(width: s(8)),
                  Icon(Icons.mark_email_unread_outlined, color: const Color(0xFF4B85EF), size: s(30)),
                  SizedBox(width: s(4)),
                  Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Icon(Icons.phone_android_rounded, color: const Color(0xFF1958B5), size: s(31)),
                      Positioned(
                        right: s(-8),
                        top: s(7),
                        child: Container(
                          padding: EdgeInsets.symmetric(horizontal: s(4), vertical: s(2)),
                          decoration: BoxDecoration(
                            color: const Color(0xFF147CFA),
                            borderRadius: BorderRadius.circular(s(8)),
                          ),
                          child: Text('SMS', style: TextStyle(color: Colors.white, fontSize: s(6.5), fontWeight: FontWeight.w800)),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          SizedBox(height: s(13)),
          Row(
            children: [
              Expanded(
                child: _OutlineAction(
                  scale: scale,
                  label: 'Précédent',
                  icon: Icons.arrow_back_rounded,
                  onPressed: submitting ? null : onPrevious,
                ),
              ),
              SizedBox(width: s(9)),
              Expanded(
                child: _OrangeAction(
                  scale: scale,
                  label: submitting ? 'Création...' : 'Créer le client',
                  icon: submitting ? null : Icons.arrow_forward_rounded,
                  loading: submitting,
                  onPressed: submitting ? null : onCreate,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Label extends StatelessWidget {
  const _Label({required this.text, required this.scale});

  final String text;
  final double scale;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: TextStyle(
        color: OvanieColors.ink,
        fontSize: 10.8 * scale,
        fontWeight: FontWeight.w700,
      ),
    );
  }
}

class _TypeButton extends StatelessWidget {
  const _TypeButton({
    required this.scale,
    required this.selected,
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final double scale;
  final bool selected;
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(s(9)),
      child: Container(
        height: s(39),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(s(9)),
          border: Border.all(
            color: selected ? const Color(0xFF096BFA) : const Color(0xFFD1D8E3),
            width: selected ? 1.2 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: selected ? const Color(0xFF086FF2) : const Color(0xFF171F31), size: s(17)),
            SizedBox(width: s(7)),
            Text(
              label,
              style: TextStyle(
                color: selected ? const Color(0xFF0868E9) : const Color(0xFF131B2B),
                fontSize: s(10.5),
                fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Input extends StatelessWidget {
  const _Input({
    required this.controller,
    required this.hint,
    required this.scale,
    this.keyboardType,
    this.obscureText = false,
    this.prefixIcon,
    this.suffixIcon,
    this.onSuffixTap,
    this.maxLines = 1,
    this.minLines = 1,
    this.textCapitalization = TextCapitalization.none,
  });

  final TextEditingController controller;
  final String hint;
  final double scale;
  final TextInputType? keyboardType;
  final bool obscureText;
  final IconData? prefixIcon;
  final IconData? suffixIcon;
  final VoidCallback? onSuffixTap;
  final int maxLines;
  final int minLines;
  final TextCapitalization textCapitalization;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      obscureText: obscureText,
      maxLines: obscureText ? 1 : maxLines,
      minLines: obscureText ? 1 : minLines,
      textCapitalization: textCapitalization,
      style: TextStyle(color: OvanieColors.ink, fontSize: s(10.8)),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: const Color(0xFF8793AA), fontSize: s(10.4), height: 1.35),
        filled: true,
        fillColor: const Color(0xFFFCFCFD),
        isDense: true,
        contentPadding: EdgeInsets.symmetric(horizontal: s(12), vertical: s(maxLines > 1 ? 10 : 12)),
        prefixIcon: prefixIcon == null
            ? null
            : Icon(prefixIcon, color: const Color(0xFF173A72), size: s(18)),
        suffixIcon: suffixIcon == null
            ? null
            : IconButton(
                onPressed: onSuffixTap,
                icon: Icon(suffixIcon, color: const Color(0xFF173A72), size: s(18)),
              ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFFD1D8E3)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFF1478F5), width: 1.2),
        ),
      ),
    );
  }
}

class _PhoneCountry extends StatelessWidget {
  const _PhoneCountry({required this.scale, required this.value, required this.onChanged});

  final double scale;
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    const countries = {
      '+225': '🇨🇮',
      '+221': '🇸🇳',
      '+223': '🇲🇱',
      '+226': '🇧🇫',
      '+233': '🇬🇭',
      '+224': '🇬🇳',
    };
    return DropdownButtonFormField<String>(
      value: value,
      isExpanded: true,
      decoration: InputDecoration(
        filled: true,
        fillColor: const Color(0xFFFCFCFD),
        isDense: true,
        contentPadding: EdgeInsets.symmetric(horizontal: s(9), vertical: s(10)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFFD1D8E3)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(s(8)),
          borderSide: const BorderSide(color: Color(0xFF1478F5)),
        ),
      ),
      dropdownColor: Colors.white,
      style: TextStyle(color: OvanieColors.ink, fontSize: s(10.5), fontWeight: FontWeight.w600),
      items: countries.entries
          .map(
            (entry) => DropdownMenuItem<String>(
              value: entry.key,
              child: Text('${entry.value}  ${entry.key}'),
            ),
          )
          .toList(growable: false),
      onChanged: (next) {
        if (next != null) onChanged(next);
      },
    );
  }
}

class _OutlineAction extends StatelessWidget {
  const _OutlineAction({
    required this.scale,
    required this.label,
    required this.onPressed,
    this.icon,
  });

  final double scale;
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return SizedBox(
      height: s(40),
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          side: const BorderSide(color: Color(0xFF075FEC), width: 1.2),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(8))),
          foregroundColor: const Color(0xFF0754D7),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (icon != null) ...[
              Icon(icon, size: s(17)),
              SizedBox(width: s(8)),
            ],
            Text(label, style: TextStyle(fontSize: s(10.8), fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

class _OrangeAction extends StatelessWidget {
  const _OrangeAction({
    required this.scale,
    required this.label,
    required this.onPressed,
    this.icon,
    this.loading = false,
  });

  final double scale;
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    double s(double value) => value * scale;
    return SizedBox(
      height: s(40),
      child: FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: OvanieColors.orange,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(s(8))),
          elevation: 0,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (loading) ...[
              SizedBox(
                width: s(15),
                height: s(15),
                child: const CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              ),
              SizedBox(width: s(8)),
            ],
            Text(label, style: TextStyle(fontSize: s(10.8), fontWeight: FontWeight.w600)),
            if (icon != null) ...[
              SizedBox(width: s(8)),
              Icon(icon, size: s(18)),
            ],
          ],
        ),
      ),
    );
  }
}

class _FormError extends StatelessWidget {
  const _FormError({required this.message, required this.scale});

  final String message;
  final double scale;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10 * scale, vertical: 8 * scale),
      decoration: BoxDecoration(
        color: const Color(0xFFFFECEC),
        borderRadius: BorderRadius.circular(8 * scale),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, color: const Color(0xFFB23B3B), size: 17 * scale),
          SizedBox(width: 7 * scale),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: const Color(0xFF9F3333), fontSize: 9.2 * scale, height: 1.3),
            ),
          ),
        ],
      ),
    );
  }
}
