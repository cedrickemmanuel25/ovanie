import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'menu_shared.dart';

class CommercialProfileScreen extends StatefulWidget {
  const CommercialProfileScreen({
    super.key,
    required this.menuService,
    required this.initialUser,
    required this.initialProfile,
    required this.unreadNotifications,
    required this.onLogout,
  });

  final MenuService menuService;
  final Map<String, dynamic> initialUser;
  final MenuProfileData? initialProfile;
  final int unreadNotifications;
  final Future<void> Function() onLogout;

  @override
  State<CommercialProfileScreen> createState() => _CommercialProfileScreenState();
}

class _CommercialProfileScreenState extends State<CommercialProfileScreen> {
  MenuProfileData? _profile;
  MenuPreferencesData? _preferences;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _profile = widget.initialProfile;
    _load();
  }

  Future<void> _load() async {
    if (mounted) setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait<dynamic>([
        widget.menuService.profile(),
        widget.menuService.preferences(),
      ]);
      if (!mounted) return;
      setState(() {
        _profile = results[0] as MenuProfileData;
        _preferences = results[1] as MenuPreferencesData;
      });
    } catch (e) {
      if (mounted) setState(() => _error = e.toString().replaceFirst('ApiException: ', ''));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  CommercialProfile get _headerProfile => menuHeaderProfile(initialUser: widget.initialUser, loaded: _profile);

  Future<void> _toggle(MenuPreferencesData next) async {
    setState(() => _preferences = next);
    try {
      final saved = await widget.menuService.savePreferences(next);
      if (mounted) setState(() => _preferences = saved);
    } catch (_) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Impossible d’enregistrer la préférence.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    return MenuPageScaffold(
      profile: _headerProfile,
      unreadNotifications: widget.unreadNotifications,
      onNotificationsTap: () => Navigator.pop(context),
      onAvatarTap: () {},
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: EdgeInsets.fromLTRB(s(16), s(12), s(16), s(102)),
          children: [
            MenuPageTitle(
              title: 'Mon profil',
              subtitle: 'Consultez et gérez vos informations personnelles et professionnelles.',
              onBack: () => Navigator.pop(context),
            ),
            SizedBox(height: s(14)),
            LoadingOrError(
              loading: _loading && _profile == null,
              error: _error,
              onRetry: _load,
              child: Column(
                children: [
                  _ProfileCard(profile: _profile, fallback: _headerProfile),
                  SizedBox(height: s(10)),
                  _PersonalInfo(profile: _profile),
                  SizedBox(height: s(10)),
                  _ProfessionalInfo(profile: _profile),
                  SizedBox(height: s(10)),
                  _PreferencesCard(
                    preferences: _preferences,
                    onNotifications: (value) {
                      final p = _preferences;
                      if (p != null) _toggle(p.copyWith(newClients: value, followUpReminders: value));
                    },
                    onShops: (value) {
                      final p = _preferences;
                      if (p != null) _toggle(p.copyWith(shopUpdates: value));
                    },
                    onSync: (value) {
                      final p = _preferences;
                      if (p != null) _toggle(p.copyWith(autoSync: value));
                    },
                  ),
                  SizedBox(height: s(10)),
                  _SecurityCard(onLogout: widget.onLogout),
                  SizedBox(height: s(10)),
                  const MenuInfoStrip(text: 'Vos informations servent à personnaliser votre espace commercial OVANIE.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileCard extends StatelessWidget {
  const _ProfileCard({required this.profile, required this.fallback});
  final MenuProfileData? profile;
  final CommercialProfile fallback;

  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final name = profile?.name ?? fallback.name;
    final avatar = profile?.avatarUrl ?? fallback.avatarUrl;
    return WhiteMenuCard(
      padding: EdgeInsets.all(s(14)),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: s(98), height: s(98), padding: EdgeInsets.all(s(3)),
                decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: const Color(0xFF126AC7), width: s(2.5))),
                child: ClipOval(
                  child: avatar == null
                      ? _FallbackAvatar(name)
                      : Image.network(avatar, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _FallbackAvatar(name)),
                ),
              ),
              SizedBox(width: s(16)),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: const Color(0xFF071A43), fontSize: s(23), fontWeight: FontWeight.w800)),
                    SizedBox(height: s(6)),
                    Wrap(
                      spacing: s(8), runSpacing: s(5),
                      children: [
                        _SoftBadge(text: profile?.jobTitle ?? 'Commercial terrain', color: const Color(0xFF1267C8)),
                        _SoftBadge(text: profile?.active == false ? 'Inactive' : 'Active', color: const Color(0xFF189455), green: true),
                      ],
                    ),
                    SizedBox(height: s(8)),
                    Row(children: [Icon(Icons.badge_outlined, size: s(17), color: const Color(0xFF14294F)), SizedBox(width: s(7)), Expanded(child: Text('Matricule : ${profile?.employeeCode ?? '—'}', style: TextStyle(color: const Color(0xFF14294F), fontSize: s(11.5))))]),
                    SizedBox(height: s(5)),
                    Row(children: [Icon(Icons.location_on_outlined, size: s(18), color: const Color(0xFF14294F)), SizedBox(width: s(7)), Expanded(child: Text('Zone : ${profile?.city ?? 'Abidjan'}', style: TextStyle(color: const Color(0xFF14294F), fontSize: s(11.5))))]),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: s(8)),
          Align(
            alignment: Alignment.centerRight,
            child: OutlinedButton.icon(
              onPressed: () => ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('La modification du profil sera disponible selon les droits de votre compte.'))),
              icon: Icon(Icons.edit_outlined, size: s(18)),
              label: const Text('Modifier le profil'),
              style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFF0D5AC7), side: const BorderSide(color: Color(0xFF0D5AC7)), padding: EdgeInsets.symmetric(horizontal: s(10), vertical: s(8))),
            ),
          ),
          SizedBox(height: s(14)),
          Row(
            children: [
              Expanded(child: _StatCard(icon: Icons.storefront_outlined, label: 'Boutiques ouvertes', value: profile?.shopsOpened ?? 0)),
              SizedBox(width: s(9)),
              Expanded(child: _StatCard(icon: Icons.inventory_2_outlined, label: 'Sessions produits', value: profile?.sessionsCount ?? 0)),
              SizedBox(width: s(9)),
              Expanded(child: _StatCard(icon: Icons.sell_outlined, label: 'Produits traités', value: profile?.productsHandled ?? 0)),
            ],
          ),
        ],
      ),
    );
  }
}

class _FallbackAvatar extends StatelessWidget {
  const _FallbackAvatar(this.name);
  final String name;
  @override
  Widget build(BuildContext context) {
    final s = menuScaleOf(context).call;
    final initials = name.split(RegExp(r'\s+')).where((e) => e.isNotEmpty).take(2).map((e) => e[0].toUpperCase()).join();
    return Container(color: const Color(0xFFDFC6A7), alignment: Alignment.center, child: Text(initials.isEmpty ? 'OC' : initials, style: TextStyle(color: const Color(0xFF784E2B), fontSize: s(23), fontWeight: FontWeight.w800)));
  }
}

class _SoftBadge extends StatelessWidget {
  const _SoftBadge({required this.text, required this.color, this.green = false});
  final String text; final Color color; final bool green;
  @override Widget build(BuildContext context) { final s=menuScaleOf(context).call; return Container(padding:EdgeInsets.symmetric(horizontal:s(8),vertical:s(4)),decoration:BoxDecoration(color:green?const Color(0xFFE6F8EC):const Color(0xFFE8F2FF),borderRadius:BorderRadius.circular(s(12)),border:green?Border.all(color:const Color(0xFF55BE79)):null),child:Text(text,style:TextStyle(color:color,fontSize:s(10.5),fontWeight:FontWeight.w600))); }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.icon, required this.label, required this.value});
  final IconData icon; final String label; final int value;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Container(padding:EdgeInsets.all(s(9)),decoration:BoxDecoration(border:Border.all(color:const Color(0xFFD0D8E3)),borderRadius:BorderRadius.circular(s(10))),child:Row(children:[Container(width:s(38),height:s(38),decoration:BoxDecoration(color:const Color(0xFFE8F2FF),borderRadius:BorderRadius.circular(s(9))),child:Icon(icon,color:const Color(0xFF0F64C8),size:s(22))),SizedBox(width:s(8)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(label,maxLines:2,style:TextStyle(color:const Color(0xFF263D65),fontSize:s(9.3))),Text('$value',style:TextStyle(color:const Color(0xFF0B55B9),fontSize:s(22),fontWeight:FontWeight.w800))]))]));}
}

class _PersonalInfo extends StatelessWidget {
  const _PersonalInfo({required this.profile});
  final MenuProfileData? profile;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return WhiteMenuCard(padding:EdgeInsets.all(s(12)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_WhiteHeader(icon:Icons.person_outline_rounded,title:'Informations personnelles'),SizedBox(height:s(8)),Container(padding:EdgeInsets.all(s(10)),decoration:BoxDecoration(border:Border.all(color:const Color(0xFFD8DEE8)),borderRadius:BorderRadius.circular(s(9))),child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[Expanded(child:_InfoPair('Nom complet',profile?.name??'—','E-mail',profile?.email.isNotEmpty==true?profile!.email:'—')),Container(width:1,height:s(70),color:const Color(0xFFE0E4EB),margin:EdgeInsets.symmetric(horizontal:s(12))),Expanded(child:_InfoPair('Téléphone',profile?.phone.isNotEmpty==true?profile!.phone:'—','WhatsApp',profile?.whatsapp.isNotEmpty==true?profile!.whatsapp:'—'))]))]));}
}

class _ProfessionalInfo extends StatelessWidget {
  const _ProfessionalInfo({required this.profile}); final MenuProfileData? profile;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return WhiteMenuCard(padding:EdgeInsets.all(s(12)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_WhiteHeader(icon:Icons.work_outline_rounded,title:'Informations professionnelles'),SizedBox(height:s(8)),Container(padding:EdgeInsets.all(s(10)),decoration:BoxDecoration(border:Border.all(color:const Color(0xFFD8DEE8)),borderRadius:BorderRadius.circular(s(9))),child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[Expanded(child:_InfoPair('Fonction',profile?.jobTitle??'Commercial terrain','Zone d’intervention',profile?.zone??'Abidjan')),Container(width:1,height:s(70),color:const Color(0xFFE0E4EB),margin:EdgeInsets.symmetric(horizontal:s(12))),Expanded(child:_InfoPair('Boutiques ouvertes','${profile?.shopsOpened ?? 0}','Date d’intégration',profile?.joinedAt??'—'))]))]));}
}

class _InfoPair extends StatelessWidget { const _InfoPair(this.a,this.av,this.b,this.bv); final String a,av,b,bv; @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(a,style:TextStyle(color:const Color(0xFF52627D),fontSize:s(9.5))),Text(av,maxLines:2,overflow:TextOverflow.ellipsis,style:TextStyle(color:const Color(0xFF071A43),fontSize:s(11.2),fontWeight:FontWeight.w700)),SizedBox(height:s(8)),Text(b,style:TextStyle(color:const Color(0xFF52627D),fontSize:s(9.5))),Text(bv,maxLines:2,overflow:TextOverflow.ellipsis,style:TextStyle(color:const Color(0xFF071A43),fontSize:s(11.2),fontWeight:FontWeight.w700))]);}}

class _WhiteHeader extends StatelessWidget { const _WhiteHeader({required this.icon,required this.title}); final IconData icon;final String title; @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Row(children:[Icon(icon,color:const Color(0xFF0D5AC7),size:s(21)),SizedBox(width:s(8)),Text(title,style:TextStyle(color:const Color(0xFF071A43),fontSize:s(14),fontWeight:FontWeight.w800))]);}}

class _PreferencesCard extends StatelessWidget {
  const _PreferencesCard({required this.preferences,required this.onNotifications,required this.onShops,required this.onSync});
  final MenuPreferencesData? preferences; final ValueChanged<bool> onNotifications,onShops,onSync;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return WhiteMenuCard(padding:EdgeInsets.all(s(12)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_WhiteHeader(icon:Icons.tune_rounded,title:'Préférences'),SizedBox(height:s(5)),_SwitchLine(icon:Icons.notifications_none_rounded,label:'Notifications terrain',value:preferences?.newClients??true,onChanged:onNotifications),_SwitchLine(icon:Icons.storefront_outlined,label:'Alertes nouvelles boutiques',value:preferences?.shopUpdates??true,onChanged:onShops),_SwitchLine(icon:Icons.cloud_sync_outlined,label:'Synchronisation automatique',value:preferences?.autoSync??true,onChanged:onSync)]));}
}

class _SwitchLine extends StatelessWidget { const _SwitchLine({required this.icon,required this.label,required this.value,required this.onChanged}); final IconData icon;final String label;final bool value;final ValueChanged<bool> onChanged; @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Container(height:s(38),decoration:const BoxDecoration(border:Border(bottom:BorderSide(color:Color(0xFFE4E8EE)))),child:Row(children:[Icon(icon,color:const Color(0xFF0C5BC7),size:s(18)),SizedBox(width:s(8)),Expanded(child:Text(label,style:TextStyle(color:const Color(0xFF20375D),fontSize:s(10.7)))),Transform.scale(scale:.78,child:Switch(value:value,onChanged:onChanged))]));}}

class _SecurityCard extends StatelessWidget {
  const _SecurityCard({required this.onLogout}); final Future<void> Function() onLogout;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return WhiteMenuCard(padding:EdgeInsets.all(s(12)),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[_WhiteHeader(icon:Icons.shield_outlined,title:'Sécurité'),SizedBox(height:s(5)),_SecurityLine(icon:Icons.lock_outline_rounded,label:'Changer le mot de passe',onTap:()=>ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Utilisez « Mot de passe oublié » à la connexion pour réinitialiser votre mot de passe.')))),_SecurityLine(icon:Icons.logout_rounded,label:'Déconnexion',danger:true,onTap:()async{await onLogout();})]));}
}

class _SecurityLine extends StatelessWidget { const _SecurityLine({required this.icon,required this.label,required this.onTap,this.danger=false});final IconData icon;final String label;final VoidCallback onTap;final bool danger; @override Widget build(BuildContext context){final s=menuScaleOf(context).call;return InkWell(onTap:onTap,child:Container(height:s(40),decoration:const BoxDecoration(border:Border(bottom:BorderSide(color:Color(0xFFE3E7ED)))),child:Row(children:[Icon(icon,color:danger?const Color(0xFFFF3B30):const Color(0xFF0D5AC7),size:s(19)),SizedBox(width:s(9)),Expanded(child:Text(label,style:TextStyle(color:const Color(0xFF1D3457),fontSize:s(10.8)))),Icon(Icons.chevron_right_rounded,color:const Color(0xFF071A43),size:s(20))])));}}
