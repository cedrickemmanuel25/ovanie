import 'dart:io';
import 'dart:ui' as ui;
import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/theme/ovanie_colors.dart';
import '../../../core/navigation/commercial_tab_bus.dart';
import '../../../core/widgets/brand_background.dart';
import '../../clients/data/clients_service.dart';
import '../../clients/presentation/clients_screen.dart';
import '../../clients/presentation/client_chrome.dart';
import '../../dashboard/models/dashboard_data.dart';
import '../../shops/data/shops_service.dart';
import '../../shops/presentation/shops_screen.dart';
import '../data/products_service.dart';
import '../models/product_data.dart';

const _navy=Color(0xFF00183D), _blue=Color(0xFF0864FF), _orange=Color(0xFFFF7100), _line=Color(0xFF2C5688), _muted=Color(0xFFB8C5DA);

class CommercialProductsScreen extends StatefulWidget{
 const CommercialProductsScreen({super.key,required this.productsService,required this.clientsService,required this.shopsService,required this.initialUser,required this.onLogout});
 final ProductsService productsService; final ClientsService clientsService; final ShopsService shopsService; final Map<String,dynamic> initialUser; final Future<void> Function() onLogout;
 @override State<CommercialProductsScreen> createState()=>_CommercialProductsScreenState();
}
class _CommercialProductsScreenState extends State<CommercialProductsScreen>{
 final q=TextEditingController(); List<CommercialShopOption> shops=[]; bool loading=true; int? selected;
 @override void initState(){super.initState();_load();}
 Future<void> _load() async {setState(()=>loading=true); try{shops=await widget.productsService.shops(q:q.text);}finally{if(mounted)setState(()=>loading=false);}}
 Future<void> _next() async { if(selected==null){_msg('Sélectionnez une boutique.');return;} final shop=shops.firstWhere((e)=>e.id==selected); await Navigator.push(context,MaterialPageRoute(builder:(_)=>SessionsScreen(service:widget.productsService,shop:shop,chrome:_chrome))); if(mounted)_load(); }
 Widget _chrome({required Widget child,required int tab})=>ProductScaffold(child:child,tab:tab,initialUser:widget.initialUser,onTab:(i)=>_tab(i));
 void _tab(int i){
  if(i==3)return;
  CommercialTabBus.request(i);
 }
 void _msg(String m)=>ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text(m)));
 @override Widget build(BuildContext context){return _chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,12,16,110),children:[
  ProductHeader(user:widget.initialUser), const SizedBox(height:18), SearchRow(controller:q,hint:'Rechercher une boutique (nom, propriétaire, ville...)',onChanged:(_)=>_load()), const SizedBox(height:22),
  Row(crossAxisAlignment:CrossAxisAlignment.start,children:[Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const Text('Choisir une boutique',style:TextStyle(color:Colors.white,fontWeight:FontWeight.w800,fontSize:28)),const SizedBox(height:4),Text('Sélectionnez la boutique dans laquelle vous souhaitez ajouter des produits.',style:TextStyle(color:_muted,fontSize:14))])),OutlinedButton.icon(style:OutlinedButton.styleFrom(foregroundColor:_orange,side:const BorderSide(color:_orange),padding:const EdgeInsets.symmetric(horizontal:15,vertical:16)),onPressed:()=>_tab(2),icon:const Icon(Icons.add),label:const Text('Ouvrir une boutique'))]),
  const SizedBox(height:12), const InfoBar(text:'Le produit sera ajouté à la boutique sélectionnée. Vous pourrez ensuite capturer ou compléter les fiches produits.'), const SizedBox(height:12),
  if(loading) const Center(child:CircularProgressIndicator()) else ...shops.map((s)=>ShopChoiceCard(shop:s,selected:selected==s.id,onTap:()=>setState(()=>selected=s.id))),
  const SizedBox(height:12),const InfoBar(text:'Astuce : choisissez d’abord la boutique, puis vous pourrez capturer plusieurs produits rapidement avant de compléter leurs informations.'),const SizedBox(height:12),
  Row(children:[Expanded(child:OutlinedButton(onPressed:()=>Navigator.pop(context),child:const Text('Retour'))),const SizedBox(width:12),Expanded(child:FilledButton(style:FilledButton.styleFrom(backgroundColor:_orange),onPressed:_next,child:const Text('Continuer')))])
 ]));}
}

class SessionsScreen extends StatefulWidget{const SessionsScreen({super.key,required this.service,required this.shop,required this.chrome});final ProductsService service;final CommercialShopOption shop;final Widget Function({required Widget child,required int tab}) chrome;@override State<SessionsScreen> createState()=>_SessionsScreenState();}
class _SessionsScreenState extends State<SessionsScreen>{List<CaptureSessionData> items=[];bool loading=true;@override void initState(){super.initState();_load();}Future<void>_load()async{setState(()=>loading=true);items=await widget.service.sessions(widget.shop.id);if(mounted)setState(()=>loading=false);} @override Widget build(BuildContext c)=>widget.chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,12,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:18),SearchRow(controller:TextEditingController(),hint:'Rechercher une boutique, un produit, une référence...',onChanged:(_){ }),const SizedBox(height:22),Row(children:[const Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('Liste des sessions',style:TextStyle(color:Colors.white,fontWeight:FontWeight.w800,fontSize:29)),Text('Suivez les sessions de capture produits et reprenez les saisies en cours.',style:TextStyle(color:_muted))])),OutlinedButton.icon(style:OutlinedButton.styleFrom(foregroundColor:_orange,side:const BorderSide(color:_orange)),onPressed:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>NewSessionScreen(service:widget.service,shop:widget.shop,chrome:widget.chrome))).then((_)=>_load()),icon:const Icon(Icons.add),label:const Text('Nouvelle session'))]),const SizedBox(height:12),SelectionBar(text:'Boutique sélectionnée : ${widget.shop.name}'),const SizedBox(height:12),if(loading)const Center(child:CircularProgressIndicator()) else if(items.isEmpty)const EmptyCard(text:'Aucune session pour cette boutique.') else ...items.map((x)=>SessionCard(data:x,onTap:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>SessionProgressScreen(service:widget.service,session:x,chrome:widget.chrome))).then((_)=>_load()))),]));}

class NewSessionScreen extends StatefulWidget {
  const NewSessionScreen({
    super.key,
    required this.service,
    required this.shop,
    required this.chrome,
  });
  final ProductsService service;
  final CommercialShopOption shop;
  final Widget Function({required Widget child, required int tab}) chrome;

  @override
  State<NewSessionScreen> createState() => _NewSessionScreenState();
}

class _NewSessionScreenState extends State<NewSessionScreen> {
  final name = TextEditingController();
  final notes = TextEditingController();
  final search = TextEditingController();
  ProductMetaData? meta;
  int? cat;
  int? sub;
  bool keep = true;
  bool saving = false;
  String captureMode = 'rapid';

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    name.text = 'Session ${now.day}/${now.month}';
    widget.service.meta().then((value) {
      if (mounted) setState(() => meta = value);
    });
  }

  @override
  void dispose() {
    name.dispose();
    notes.dispose();
    search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get children {
    final category = meta?.categories.where((e) => e['id'] == cat).firstOrNull;
    if (category == null) return const [];
    return ((category['children'] as List?) ?? const [])
        .map((e) => Map<String, dynamic>.from(e as Map))
        .toList();
  }

  Future<void> start() async {
    if (cat == null) {
      _message('Choisissez une catégorie.');
      return;
    }
    if (sub == null) {
      _message('Choisissez une sous-catégorie.');
      return;
    }
    setState(() => saving = true);
    try {
      final session = await widget.service.createSession(
        shopId: widget.shop.id,
        name: name.text.trim(),
        categoryId: cat,
        subcategoryId: sub,
        notes: notes.text.trim(),
        keepCategory: keep,
      );
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => CameraCaptureScreen(
            service: widget.service,
            session: session,
            chrome: widget.chrome,
          ),
        ),
      );
    } catch (error) {
      _message('$error');
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  void _message(String message) => ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );

  String _dateLabel() {
    const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    final now = DateTime.now();
    return '${now.day.toString().padLeft(2, '0')} ${months[now.month - 1]} ${now.year}';
  }

  @override
  Widget build(BuildContext context) {
    final scope = _ProductChromeScope.maybeOf(context);
    final user = scope?.user ?? const <String, dynamic>{};
    final profile = CommercialProfile.fromJson(user);
    final commercialName = profile.name.isEmpty ? 'Commercial OVANIE' : profile.name;

    return widget.chrome(
      tab: 3,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 110),
        children: [
          const ProductHeader(user: {}),
          const SizedBox(height: 14),
          SearchRow(
            controller: search,
            hint: 'Rechercher une boutique, un produit, une référence...',
            onChanged: (_) {},
          ),
          const SizedBox(height: 20),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Nouvelle session',
                      style: TextStyle(color: Colors.white, fontSize: 29, fontWeight: FontWeight.w800),
                    ),
                    SizedBox(height: 3),
                    Text(
                      'Préparez une session de capture pour photographier plusieurs produits avant de compléter leurs fiches.',
                      style: TextStyle(color: _muted, fontSize: 13.5, height: 1.25),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  foregroundColor: _orange,
                  side: const BorderSide(color: _orange),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                ),
                onPressed: () => Navigator.pop(context),
                child: const Text('Voir les sessions'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SelectionBar(text: 'Boutique sélectionnée : ${widget.shop.name}'),
          const SizedBox(height: 12),
          WhiteCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const StepTitle(n: 1, title: 'Informations de la session'),
                Field(label: 'Nom de la session', controller: name),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: _ReadOnlySessionField(label: 'Commercial', value: commercialName),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _ReadOnlySessionField(
                        label: 'Date',
                        value: _dateLabel(),
                        trailing: Icons.calendar_month_outlined,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                const StepTitle(n: 2, title: 'Préparation de la capture'),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: DropdownField(
                        label: 'Catégorie principale',
                        value: cat,
                        items: meta?.categories ?? const [],
                        onChanged: (value) => setState(() {
                          cat = value;
                          sub = null;
                        }),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownField(
                        label: 'Sous-catégorie',
                        value: sub,
                        items: children,
                        onChanged: (value) => setState(() => sub = value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  value: keep,
                  activeColor: _blue,
                  onChanged: (value) => setState(() => keep = value),
                  title: const Text(
                    'Conserver cette catégorie pour les prochains produits',
                    style: TextStyle(color: Color(0xFF12346A), fontSize: 13),
                  ),
                  secondary: const Icon(Icons.info_outline, color: Color(0xFF315B8A), size: 18),
                ),
                const SizedBox(height: 4),
                const Text('Mode de capture', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                const SizedBox(height: 7),
                const _CaptureModeCard(
                  selected: true,
                  icon: Icons.camera_alt_outlined,
                  title: 'Capture rapide',
                  subtitle: 'Photographiez chaque produit à la suite : la photo est enregistrée puis l’appareil se rouvre aussitôt pour le produit suivant, sans perdre les photos déjà prises. Vous complétez les fiches plus tard.',
                  onTap: null,
                ),
                const SizedBox(height: 5),
                const StepTitle(n: 3, title: 'Résumé de démarrage'),
                const InfoBar(
                  light: true,
                  text: '0 produit capturé\nLes produits seront enregistrés en brouillon\nVous pourrez compléter les informations plus tard',
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(48),
                          padding: const EdgeInsets.symmetric(horizontal: 6),
                        ),
                        onPressed: () => Navigator.pop(context),
                        child: const FittedBox(fit: BoxFit.scaleDown, child: Text('Annuler')),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: _orange,
                          minimumSize: const Size.fromHeight(48),
                          padding: const EdgeInsets.symmetric(horizontal: 6),
                        ),
                        onPressed: saving ? null : start,
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(saving ? 'Création...' : 'Démarrer la session'),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          const InfoBar(
            text: 'Astuce : utilisez une seule catégorie si vous photographiez plusieurs produits similaires, puis changez-la seulement quand vous passez à une autre famille de produits.',
          ),
        ],
      ),
    );
  }
}

class _ReadOnlySessionField extends StatelessWidget {
  const _ReadOnlySessionField({required this.label, required this.value, this.trailing});
  final String label;
  final String value;
  final IconData? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            isDense: true,
            filled: true,
            fillColor: const Color(0xFFF8FAFD),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 15),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Color(0xFF12346A), fontSize: 12.5, fontWeight: FontWeight.w600),
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 3),
                Icon(trailing, size: 17, color: const Color(0xFF315B8A)),
              ],
            ],
          ),
        ),
      );
}

class _CaptureModeCard extends StatelessWidget {
  const _CaptureModeCard({
    required this.selected,
    required this.icon,
    required this.title,
    required this.subtitle,
    this.onTap,
  });
  final bool selected;
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(11),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFF7FAFF) : Colors.white,
            borderRadius: BorderRadius.circular(11),
            border: Border.all(color: selected ? _blue : const Color(0xFFD5DEE9), width: selected ? 1.7 : 1),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 19,
                backgroundColor: selected ? const Color(0xFFE5F0FF) : const Color(0xFFF0E9FF),
                child: Icon(icon, size: 19, color: selected ? _blue : const Color(0xFF6F41DB)),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: Color(0xFF00133A), fontSize: 13.5, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 3),
                    Text(subtitle, style: const TextStyle(color: Color(0xFF315B8A), fontSize: 11, height: 1.3)),
                  ],
                ),
              ),
              Icon(Icons.check_circle_rounded, color: selected ? _blue : Colors.transparent, size: 18),
            ],
          ),
        ),
      );
}

class CameraCaptureScreen extends StatefulWidget{const CameraCaptureScreen({super.key,required this.service,required this.session,required this.chrome});final ProductsService service;final CaptureSessionData session;final Widget Function({required Widget child,required int tab}) chrome;@override State<CameraCaptureScreen> createState()=>_CameraCaptureScreenState();}

class _CameraCaptureScreenState extends State<CameraCaptureScreen> with WidgetsBindingObserver {
  final picker = ImagePicker();
  CameraController? _controller;
  List<CameraDescription> _cameras = const [];
  int _cameraIndex = 0;
  String? _cameraError;
  bool busy = false;
  bool _showGrid = false;
  FlashMode _flash = FlashMode.auto;
  double _minZoom = 1;
  double _maxZoom = 1;
  double _zoom = 1;
  String _captureKind = 'produit';
  int _capturedCount = 0;
  String? _lastThumbPath;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _setupCamera();
  }

  Future<void> _setupCamera() async {
    try {
      _cameras = await availableCameras();
      if (_cameras.isEmpty) {
        if (!mounted) return;
        setState(() => _cameraError = 'Aucune caméra détectée sur cet appareil.');
        return;
      }
      await _openCamera(_cameraIndex);
    } catch (error) {
      if (!mounted) return;
      setState(() => _cameraError = 'Impossible d’accéder à la caméra : $error');
    }
  }

  Future<void> _openCamera(int index) async {
    final previous = _controller;
    final controller = CameraController(_cameras[index], ResolutionPreset.medium, enableAudio: false);
    try {
      await controller.initialize();
      _minZoom = await controller.getMinZoomLevel();
      _maxZoom = await controller.getMaxZoomLevel();
      try {
        await controller.setFlashMode(_flash);
      } catch (_) {}
      await previous?.dispose();
      if (!mounted) {
        await controller.dispose();
        return;
      }
      setState(() {
        _controller = controller;
        _cameraIndex = index;
        _zoom = 1;
        _cameraError = null;
      });
    } catch (error) {
      await controller.dispose();
      if (!mounted) return;
      setState(() => _cameraError = 'Impossible d’ouvrir la caméra : $error');
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized) return;
    if (state == AppLifecycleState.inactive || state == AppLifecycleState.paused) {
      controller.dispose();
      _controller = null;
    } else if (state == AppLifecycleState.resumed) {
      _openCamera(_cameraIndex);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller?.dispose();
    super.dispose();
  }

  Future<void> _switchCamera() async {
    if (_cameras.length < 2) return;
    await _openCamera((_cameraIndex + 1) % _cameras.length);
  }

  Future<void> _toggleFlash() async {
    const order = [FlashMode.auto, FlashMode.always, FlashMode.off];
    final next = order[(order.indexOf(_flash) + 1) % order.length];
    setState(() => _flash = next);
    try {
      await _controller?.setFlashMode(next);
    } catch (_) {}
  }

  String get _flashLabel => switch (_flash) {
        FlashMode.always => 'Flash',
        FlashMode.off => 'Off',
        _ => 'Auto',
      };

  IconData get _flashIcon => switch (_flash) {
        FlashMode.always => Icons.flash_on_rounded,
        FlashMode.off => Icons.flash_off_rounded,
        _ => Icons.flash_auto_rounded,
      };

  Future<void> _cycleZoom() async {
    const steps = [1.0, 2.0, 3.0];
    final available = steps.where((step) => step >= _minZoom && step <= _maxZoom).toList();
    if (available.isEmpty) return;
    final currentIndex = available.indexOf(_zoom);
    final next = available[(currentIndex + 1) % available.length];
    setState(() => _zoom = next);
    try {
      await _controller?.setZoomLevel(next);
    } catch (_) {}
  }

  Future<void> _uploadAndContinue(String filePath) async {
    setState(() => busy = true);
    try {
      final product = await widget.service.capture(
        sessionId: widget.session.id,
        filePath: filePath,
        categoryId: widget.session.categoryId ?? 0,
        subcategoryId: widget.session.subcategoryId,
      );
      if (!mounted) return;
      setState(() {
        _capturedCount++;
        _lastThumbPath = filePath;
      });
      if (_captureKind == 'multiple') {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Photo $_capturedCount enregistrée ✓ — prête pour le produit suivant'),
            duration: const Duration(milliseconds: 1400),
            behavior: SnackBarBehavior.floating,
          ),
        );
      } else {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => CapturedScreen(
              service: widget.service,
              session: widget.session,
              product: product,
              localPath: filePath,
              chrome: widget.chrome,
            ),
          ),
        );
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Échec de l’enregistrement de la photo : $error'), behavior: SnackBarBehavior.floating),
      );
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _shoot() async {
    final controller = _controller;
    if (controller == null || !controller.value.isInitialized || busy) return;
    try {
      final file = await controller.takePicture();
      await _uploadAndContinue(file.path);
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Impossible de prendre la photo : $error'), behavior: SnackBarBehavior.floating),
      );
    }
  }

  Future<void> _fromGallery() async {
    final file = await picker.pickImage(source: ImageSource.gallery, imageQuality: 88, maxWidth: 1600);
    if (file == null) return;
    await _uploadAndContinue(file.path);
  }

  @override
  Widget build(BuildContext context) {
    final controller = _controller;
    final ready = controller != null && controller.value.isInitialized;
    final previewHeight = MediaQuery.sizeOf(context).height * .38;
    return widget.chrome(
      tab: 3,
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                const Expanded(
                  child: Text('Prendre une photo', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800), overflow: TextOverflow.ellipsis),
                ),
                if (_capturedCount > 0) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                    decoration: BoxDecoration(color: const Color(0xFF0F3A21), borderRadius: BorderRadius.circular(20), border: Border.all(color: const Color(0xFF1E8A4C))),
                    child: Text('✓ $_capturedCount', style: const TextStyle(color: Color(0xFF6FE39B), fontSize: 11, fontWeight: FontWeight.w700)),
                  ),
                  const SizedBox(width: 8),
                ],
                OutlinedButton.icon(
                  onPressed: ready ? _toggleFlash : null,
                  style: OutlinedButton.styleFrom(foregroundColor: Colors.white, side: const BorderSide(color: _line), padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8)),
                  icon: Icon(_flashIcon, size: 16),
                  label: Text(_flashLabel, style: const TextStyle(fontSize: 12)),
                ),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: previewHeight,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: Container(
                  color: const Color(0xFF0A234D),
                  child: Stack(
                    fit: StackFit.expand,
                    children: [
                      if (ready)
                        CameraPreview(controller)
                      else if (_cameraError != null)
                        Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(_cameraError!, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white70)),
                          ),
                        )
                      else
                        const Center(child: CircularProgressIndicator(color: Colors.white)),
                      if (ready && _showGrid) const Positioned.fill(child: IgnorePointer(child: CustomPaint(painter: _GridPainter()))),
                      if (ready)
                        Positioned.fill(
                          child: IgnorePointer(
                            child: Padding(
                              padding: const EdgeInsets.all(22),
                              child: CustomPaint(painter: _CornerFramePainter()),
                            ),
                          ),
                        ),
                      if (ready)
                        Positioned(
                          bottom: 10,
                          left: 0,
                          right: 0,
                          child: Center(
                            child: Text(
                              '${widget.session.category} • ${widget.session.subcategory}',
                              style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600, shadows: [Shadow(blurRadius: 6, color: Colors.black87)]),
                            ),
                          ),
                        ),
                      if (busy) Positioned.fill(child: Container(color: Colors.black45, child: const Center(child: CircularProgressIndicator(color: Colors.white)))),
                    ],
                  ),
                ),
              ),
            ),
            if (ready) ...[
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _SideButton(icon: Icons.flip_camera_ios_outlined, label: 'Retourner', onTap: _cameras.length > 1 ? _switchCamera : null),
                  _SideButton(icon: Icons.grid_on_rounded, label: 'Grille', active: _showGrid, onTap: () => setState(() => _showGrid = !_showGrid)),
                  _SideButton(icon: Icons.zoom_in_rounded, label: '${_zoom.toStringAsFixed(0)}x', onTap: _maxZoom > _minZoom ? _cycleZoom : null),
                ],
              ),
            ],
            const SizedBox(height: 8),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                OutlinedButton.icon(
                  onPressed: busy ? null : _fromGallery,
                  icon: const Icon(Icons.photo_library_outlined, size: 18),
                  label: const Text('Galerie'),
                ),
                GestureDetector(
                  onTap: (busy || !ready) ? null : _shoot,
                  child: Container(
                    width: 60,
                    height: 60,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: (busy || !ready) ? Colors.white38 : Colors.white,
                      border: Border.all(color: Colors.white, width: 4),
                      boxShadow: const [BoxShadow(color: Colors.black38, blurRadius: 8)],
                    ),
                  ),
                ),
                _lastThumbPath != null
                    ? ClipRRect(
                        borderRadius: BorderRadius.circular(10),
                        child: Image.file(File(_lastThumbPath!), width: 42, height: 42, fit: BoxFit.cover),
                      )
                    : const SizedBox(width: 42, height: 42),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(child: _CaptureKindTab(label: 'Photo produit', icon: Icons.inventory_2_outlined, selected: _captureKind == 'produit', onTap: () => setState(() => _captureKind = 'produit'))),
                const SizedBox(width: 7),
                Expanded(child: _CaptureKindTab(label: 'Photo étiquette', icon: Icons.local_offer_outlined, selected: _captureKind == 'etiquette', onTap: () => setState(() => _captureKind = 'etiquette'))),
                const SizedBox(width: 7),
                Expanded(child: _CaptureKindTab(label: 'Photo multiple', icon: Icons.burst_mode_outlined, selected: _captureKind == 'multiple', onTap: () => setState(() => _captureKind = 'multiple'))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SideButton extends StatelessWidget {
  const _SideButton({required this.icon, required this.label, this.onTap, this.active = false});
  final IconData icon;
  final String label;
  final VoidCallback? onTap;
  final bool active;
  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          width: 52,
          padding: const EdgeInsets.symmetric(vertical: 7),
          decoration: BoxDecoration(
            color: active ? _blue.withOpacity(.85) : Colors.black.withOpacity(.42),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, color: onTap == null ? Colors.white30 : Colors.white, size: 19),
              const SizedBox(height: 2),
              Text(label, style: TextStyle(color: onTap == null ? Colors.white30 : Colors.white, fontSize: 9.5)),
            ],
          ),
        ),
      );
}

class _CaptureKindTab extends StatelessWidget {
  const _CaptureKindTab({required this.label, required this.icon, required this.selected, required this.onTap});
  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 6),
          decoration: BoxDecoration(
            color: selected ? _blue : const Color(0xFF0D2C58),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: selected ? _blue : _line),
          ),
          child: Column(
            children: [
              Icon(icon, color: Colors.white, size: 17),
              const SizedBox(height: 3),
              Text(label, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600)),
            ],
          ),
        ),
      );
}

class _GridPainter extends CustomPainter {
  const _GridPainter();
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withOpacity(.4)
      ..strokeWidth = 1;
    for (var i = 1; i < 3; i++) {
      final x = size.width * i / 3;
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
      final y = size.height * i / 3;
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _CornerFramePainter extends CustomPainter {
  const _CornerFramePainter();
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = _blue
      ..strokeWidth = 3
      ..strokeCap = StrokeCap.round
      ..style = PaintingStyle.stroke;
    const length = 26.0;
    canvas
      ..drawLine(Offset.zero, const Offset(length, 0), paint)
      ..drawLine(Offset.zero, const Offset(0, length), paint)
      ..drawLine(Offset(size.width, 0), Offset(size.width - length, 0), paint)
      ..drawLine(Offset(size.width, 0), Offset(size.width, length), paint)
      ..drawLine(Offset(0, size.height), Offset(length, size.height), paint)
      ..drawLine(Offset(0, size.height), Offset(0, size.height - length), paint)
      ..drawLine(Offset(size.width, size.height), Offset(size.width - length, size.height), paint)
      ..drawLine(Offset(size.width, size.height), Offset(size.width, size.height - length), paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class CapturedScreen extends StatelessWidget{const CapturedScreen({super.key,required this.service,required this.session,required this.product,required this.localPath,required this.chrome});final ProductsService service;final CaptureSessionData session;final CapturedProductData product;final String localPath;final Widget Function({required Widget child,required int tab}) chrome;@override Widget build(BuildContext c)=>chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:20),Container(padding:const EdgeInsets.symmetric(horizontal:12,vertical:9),decoration:BoxDecoration(color:const Color(0xFF0F3A21),borderRadius:BorderRadius.circular(10),border:Border.all(color:const Color(0xFF1E8A4C))),child:const Row(children:[Icon(Icons.check_circle_rounded,color:Color(0xFF6FE39B),size:18),SizedBox(width:8),Expanded(child:Text('Photo enregistrée avec succès',style:TextStyle(color:Color(0xFF6FE39B),fontWeight:FontWeight.w700)))])),const SizedBox(height:12),const Text('Produit capturé',style:TextStyle(color:Colors.white,fontSize:28,fontWeight:FontWeight.w800)),const Text('Vérifiez rapidement les informations puis passez au produit suivant.',style:TextStyle(color:_muted)),const SizedBox(height:12),SelectionBar(text:'Boutique : ${session.shopName}\nSession : ${session.name}'),const SizedBox(height:12),WhiteCard(child:Column(children:[ClipRRect(borderRadius:BorderRadius.circular(14),child:Image.file(File(localPath),height:300,width:double.infinity,fit:BoxFit.cover)),const SizedBox(height:12),Row(children:[Expanded(child:InfoTile(title:'Catégorie principale',value:product.category)),Expanded(child:InfoTile(title:'Sous-catégorie',value:product.subcategory))]),const SizedBox(height:12),const InfoBar(light:true,text:'Statut : Brouillon à compléter plus tard\nMode : Capture rapide'),const SizedBox(height:14),Row(children:[Expanded(child:OutlinedButton.icon(onPressed:()=>Navigator.pop(c),icon:const Icon(Icons.replay),label:const Text('Reprendre la photo'))),const SizedBox(width:10),Expanded(child:FilledButton(style:FilledButton.styleFrom(backgroundColor:_orange),onPressed:()=>Navigator.pop(c),child:const Text('Enregistrer & produit suivant')))]),const SizedBox(height:10),OutlinedButton(onPressed:()async{await service.finishSession(session.id);if(!c.mounted)return;Navigator.pushReplacement(c,MaterialPageRoute(builder:(_)=>EndSessionScreen(service:service,session:session,chrome:chrome)));},child:const Text('Terminer la session'))]))]));}

class SessionProgressScreen extends StatefulWidget{const SessionProgressScreen({super.key,required this.service,required this.session,required this.chrome});final ProductsService service;final CaptureSessionData session;final Widget Function({required Widget child,required int tab}) chrome;@override State<SessionProgressScreen> createState()=>_SessionProgressScreenState();}
class _SessionProgressScreenState extends State<SessionProgressScreen>{Map<String,dynamic>? data;bool loading=true;@override void initState(){super.initState();_load();}Future<void>_load()async{data=await widget.service.session(widget.session.id);if(mounted)setState(()=>loading=false);}@override Widget build(BuildContext c){final products=((data?['products'] as List?) ?? const []).map((e)=>CapturedProductData.fromJson(Map<String,dynamic>.from(e as Map))).toList();final sessionJson = data?['session'];final CaptureSessionData s = sessionJson is Map ? CaptureSessionData.fromJson(Map<String,dynamic>.from(sessionJson)) : widget.session;return widget.chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:20),Row(children:[const Expanded(child:Text('Session en cours',style:TextStyle(color:Colors.white,fontSize:30,fontWeight:FontWeight.w800))),OutlinedButton(style:OutlinedButton.styleFrom(foregroundColor:_orange,side:const BorderSide(color:_orange)),onPressed:()async{await widget.service.finishSession(s.id);if(!c.mounted)return;Navigator.pushReplacement(c,MaterialPageRoute(builder:(_)=>EndSessionScreen(service:widget.service,session:s,chrome:widget.chrome)));},child:const Text('Terminer la session'))]),const SizedBox(height:12),if(loading)const Center(child:CircularProgressIndicator())else WhiteCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(s.name,style:const TextStyle(fontSize:20,fontWeight:FontWeight.w800)),Text('${s.date} • ${s.shopName}',style:const TextStyle(color:Color(0xFF3A5680))),const SizedBox(height:12),LinearProgressIndicator(value:s.progress,minHeight:8,borderRadius:BorderRadius.circular(20)),const SizedBox(height:14),Row(mainAxisAlignment:MainAxisAlignment.spaceAround,children:[Metric(n:s.captured,label:'produits capturés'),Metric(n:s.completed,label:'complétés'),Metric(n:s.drafts,label:'brouillons')]),const Divider(height:28),...products.map((p)=>ProductRow(p:p,onTap:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>ProductWizardScreen(service:widget.service,productId:p.id,session:s,chrome:widget.chrome))))),const SizedBox(height:14),FilledButton.icon(style:FilledButton.styleFrom(backgroundColor:_orange,minimumSize:const Size.fromHeight(48)),onPressed:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>CameraCaptureScreen(service:widget.service,session:s,chrome:widget.chrome))),icon:const Icon(Icons.add),label:const Text('Ajouter un autre produit'))]))]));}}

class EndSessionScreen extends StatelessWidget{const EndSessionScreen({super.key,required this.service,required this.session,required this.chrome});final ProductsService service;final CaptureSessionData session;final Widget Function({required Widget child,required int tab}) chrome;@override Widget build(BuildContext c)=>chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:20),const Text('Fin de session',style:TextStyle(color:Colors.white,fontSize:30,fontWeight:FontWeight.w800)),const Text('Votre session de capture est terminée. Vous pouvez maintenant compléter les fiches produits ou revenir plus tard.',style:TextStyle(color:_muted)),const SizedBox(height:12),SelectionBar(text:'Boutique sélectionnée : ${session.shopName}'),const SizedBox(height:12),WhiteCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(session.name,style:const TextStyle(fontSize:22,fontWeight:FontWeight.w800)),const SizedBox(height:12),const Text('Résumé de la session',style:TextStyle(fontSize:18,fontWeight:FontWeight.w800)),const SizedBox(height:8),Row(mainAxisAlignment:MainAxisAlignment.spaceAround,children:[Metric(n:session.captured,label:'produits capturés'),Metric(n:session.completed,label:'déjà complétés'),Metric(n:(session.captured-session.completed).clamp(0,9999),label:'à compléter')]),const SizedBox(height:18),const InfoBar(light:true,text:'Prochaines actions recommandées\n• Compléter les produits restants\n• Vérifier les photos si nécessaire\n• Publier les produits une fois les fiches terminées')])),const SizedBox(height:12),FilledButton(style:FilledButton.styleFrom(backgroundColor:_orange,minimumSize:const Size.fromHeight(52)),onPressed:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>IncompleteProductsScreen(service:service,session:session,chrome:chrome))),child:const Text('Compléter les produits'))]));}

class IncompleteProductsScreen extends StatefulWidget{const IncompleteProductsScreen({super.key,required this.service,required this.session,required this.chrome});final ProductsService service;final CaptureSessionData session;final Widget Function({required Widget child,required int tab}) chrome;@override State<IncompleteProductsScreen> createState()=>_IncompleteProductsScreenState();}
class _IncompleteProductsScreenState extends State<IncompleteProductsScreen>{Map<String,dynamic>? data;String? error;@override void initState(){super.initState();_load();}Future<void>_load()async{setState(()=>error=null);try{data=await widget.service.incomplete(widget.session.id);}catch(e){error='$e';}if(mounted)setState((){});}@override Widget build(BuildContext c){final products=((data?['products'] as List?) ?? const []).map((e)=>CapturedProductData.fromJson(Map<String,dynamic>.from(e as Map))).toList();return widget.chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:20),const Text('Produits à compléter',style:TextStyle(color:Colors.white,fontSize:30,fontWeight:FontWeight.w800)),const Text('Complétez les fiches produits capturés avant leur publication.',style:TextStyle(color:_muted)),const SizedBox(height:12),SelectionBar(text:'Boutique : ${widget.session.shopName}   |   Session : ${widget.session.name}'),const SizedBox(height:12),WhiteCard(child:Column(children:[Row(mainAxisAlignment:MainAxisAlignment.spaceAround,children:[Metric(n:widget.session.captured,label:'capturés'),Metric(n:widget.session.completed,label:'complétés'),Metric(n:products.length,label:'à compléter')]),const Divider(height:26),if(error!=null)Column(children:[Text('Erreur : $error',textAlign:TextAlign.center),const SizedBox(height:10),FilledButton(onPressed:_load,child:const Text('Réessayer'))])else if(data==null)const CircularProgressIndicator()else...products.map((p)=>ProductRow(p:p,action:'Compléter',onTap:()=>Navigator.push(c,MaterialPageRoute(builder:(_)=>ProductWizardScreen(service:widget.service,productId:p.id,session:widget.session,chrome:widget.chrome))).then((_)=>_load()))) ]))]));}}

const _unitLabels = {'sac':'Sac','tonne':'Tonne','m3':'Mètre cube (m³)','m2':'Mètre carré (m²)','ml':'Mètre linéaire (ml)','piece':'Pièce','palette':'Palette','rouleau':'Rouleau','seau':'Seau','carton':'Carton','paquet':'Paquet','barre':'Barre','bidon':'Bidon','kg':'Kilogramme (kg)','litre':'Litre'};
const _saleTypeLabels = {'standard':'Vente à l’unité','lot':'Vente par lot','weight':'Vente au poids','meter':'Vente au mètre','surface':'Vente au m²','volume':'Vente au m³'};
const _warrantyLabels = {'aucune':'Aucune garantie','3_mois':'3 mois','6_mois':'6 mois','1_an':'1 an','2_ans':'2 ans','5_ans':'5 ans'};
const _usageAreaLabels = {'construction':'Construction','renovation':'Rénovation','interieur':'Intérieur','exterieur':'Extérieur','chantier':'Chantier','professionnel':'Usage professionnel'};

class ProductWizardScreen extends StatefulWidget{const ProductWizardScreen({super.key,required this.service,required this.productId,required this.session,required this.chrome});final ProductsService service;final int productId;final CaptureSessionData session;final Widget Function({required Widget child,required int tab}) chrome;@override State<ProductWizardScreen> createState()=>_ProductWizardScreenState();}
class _ProductWizardScreenState extends State<ProductWizardScreen>{
  int step=1;
  Map<String,dynamic> p={};
  Map<String,dynamic> extra={};
  bool loading=true,saving=false;
  String? error;
  final c=<String,TextEditingController>{};
  @override void initState(){super.initState();_load();}
  TextEditingController ctl(String k)=>c.putIfAbsent(k,()=>TextEditingController(text:'${p[k]??''}'));
  Future<void>_load()async{
    setState((){loading=true;error=null;});
    try{
      final j=await widget.service.product(widget.productId);
      p=Map<String,dynamic>.from((j['product'] as Map?) ?? const {});
      extra={
        'unit':p['unit'],
        'sale_type':p['sale_type'],
        'warranty':p['warranty'],
        'usage_area':p['usage_area'],
        'fragile':p['fragile']==true,
        'requires_unloading':p['requires_unloading']==true,
      };
    }catch(e){error='$e';}
    finally{if(mounted)setState(()=>loading=false);}
  }
  Future<void>next()async{
    setState(()=>saving=true);
    final body=<String,dynamic>{};
    for(final e in c.entries)body[e.key]=e.value.text;
    body.addAll(extra);
    try{
      await widget.service.saveStep(widget.productId,step,body);
      if(step<5){setState((){step++;c.clear();});await _load();}
      else{if(!mounted)return;Navigator.push(context,MaterialPageRoute(builder:(_)=>ProductRecapScreen(service:widget.service,productId:widget.productId,session:widget.session,chrome:widget.chrome)));}
    }catch(e){
      if(mounted)ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text('$e'),behavior:SnackBarBehavior.floating));
    }finally{if(mounted)setState(()=>saving=false);}
  }
  @override Widget build(BuildContext cxt)=>widget.chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:18),const Text('Ajouter un produit',style:TextStyle(color:Colors.white,fontSize:29,fontWeight:FontWeight.w800)),Text(['','Nom, catégorie et description du produit.','Prix de vente, stock et unité de mesure.','Détails techniques et garantie.','Poids, dimensions et conditions de livraison.','Photos et vidéo du produit.'][step],style:const TextStyle(color:_muted)),const SizedBox(height:12),SelectionBar(text:'Boutique : ${widget.session.shopName}   |   Session : ${widget.session.name}'),const SizedBox(height:12),if(loading)const Center(child:CircularProgressIndicator())else if(error!=null)WhiteCard(child:Column(children:[Text('Impossible de charger le produit : $error',textAlign:TextAlign.center),const SizedBox(height:12),FilledButton(onPressed:_load,child:const Text('Réessayer'))]))else WhiteCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[StepStrip(active:step),const SizedBox(height:18),ProductIdentity(product:p),const SizedBox(height:16),..._fields(),const SizedBox(height:18),Row(children:[Expanded(child:OutlinedButton(onPressed:step==1?()=>Navigator.pop(cxt):()=>setState((){step--;c.clear();}),child:const Text('Retour'))),const SizedBox(width:12),Expanded(child:FilledButton(style:FilledButton.styleFrom(backgroundColor:_orange),onPressed:saving?null:next,child:Text(saving?'Enregistrement...':(step==5?'Récapitulatif':'Suivant'))))])]))]));
  List<Widget>_fields(){
    switch(step){
      case 1:
        return [
          Field(label:'Nom du produit',controller:ctl('name')),
          _ReadonlyField(label:'Catégorie principale',value:'${p['category']??''}'),
          _ReadonlyField(label:'Sous-catégorie',value:'${p['subcategory']??''}'),
          Field(label:'Marque (optionnel)',controller:ctl('brand')),
          Field(label:'Description courte',controller:ctl('short_description'),maxLines:2),
          Field(label:'Mots-clés (optionnel, séparés par une virgule)',controller:ctl('keywords')),
          Field(label:'Description détaillée',controller:ctl('description'),maxLines:5),
        ];
      case 2:
        return [
          Row(children:[Expanded(child:Field(label:'Prix normal (FCFA)',controller:ctl('price'),keyboard:TextInputType.number)),const SizedBox(width:12),Expanded(child:Field(label:'Prix promo (optionnel)',controller:ctl('promo_price'),keyboard:TextInputType.number))]),
          Row(children:[Expanded(child:Field(label:'Stock disponible',controller:ctl('stock'),keyboard:TextInputType.number)),const SizedBox(width:12),Expanded(child:_EnumField(label:'Unité de vente',value:extra['unit']?.toString(),options:_unitLabels,onChanged:(v)=>setState(()=>extra['unit']=v)))]),
          Row(children:[Expanded(child:Field(label:'Libellé (optionnel, ex: Sac de 50 kg)',controller:ctl('unit_label'))),const SizedBox(width:12),Expanded(child:Field(label:'Quantité minimum',controller:ctl('min_order_quantity'),keyboard:TextInputType.number))]),
          _EnumField(label:'Type de vente',value:extra['sale_type']?.toString(),options:_saleTypeLabels,onChanged:(v)=>setState(()=>extra['sale_type']=v)),
        ];
      case 3:
        return [
          _EnumField(label:'Usage recommandé',value:extra['usage_area']?.toString(),options:_usageAreaLabels,onChanged:(v)=>setState(()=>extra['usage_area']=v)),
          Field(label:'Détails techniques',controller:ctl('technical_details'),maxLines:5),
          _EnumField(label:'Garantie',value:extra['warranty']?.toString(),options:_warrantyLabels,onChanged:(v)=>setState(()=>extra['warranty']=v)),
        ];
      case 4:
        return [
          Row(children:[Expanded(child:Field(label:'Poids (kg)',controller:ctl('weight_kg'),keyboard:TextInputType.number)),const SizedBox(width:12),Expanded(child:Field(label:'Longueur (cm)',controller:ctl('length_cm'),keyboard:TextInputType.number))]),
          Row(children:[Expanded(child:Field(label:'Largeur (cm)',controller:ctl('width_cm'),keyboard:TextInputType.number)),const SizedBox(width:12),Expanded(child:Field(label:'Hauteur (cm)',controller:ctl('height_cm'),keyboard:TextInputType.number))]),
          _ToggleField(label:'Produit fragile',value:extra['fragile']==true,onChanged:(v)=>setState(()=>extra['fragile']=v)),
          _ToggleField(label:'Déchargement requis',value:extra['requires_unloading']==true,onChanged:(v)=>setState(()=>extra['requires_unloading']=v)),
          if(extra['requires_unloading']==true)Field(label:'Détails du déchargement',controller:ctl('unloading_instructions'),maxLines:2),
        ];
      default:
        return [MediaStep(service:widget.service,productId:widget.productId,existing:'${p['image_url']??''}')];
    }
  }
}

class _ReadonlyField extends StatelessWidget{
  const _ReadonlyField({required this.label,required this.value});
  final String label; final String value;
  @override Widget build(BuildContext c) => Padding(padding:const EdgeInsets.only(bottom:12),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
    Text(label,style:const TextStyle(fontWeight:FontWeight.w600,fontSize:13,color:Color(0xFF274B79))),
    const SizedBox(height:6),
    Container(width:double.infinity,padding:const EdgeInsets.symmetric(horizontal:13,vertical:15),decoration:BoxDecoration(color:const Color(0xFFF3F6FB),borderRadius:BorderRadius.circular(10),border:Border.all(color:const Color(0xFFD8E0EA))),child:Text(value.isEmpty?'—':value,style:const TextStyle(color:Color(0xFF00133A)))),
  ]));
}

class _EnumField extends StatelessWidget{
  const _EnumField({required this.label,required this.value,required this.options,required this.onChanged});
  final String label; final String? value; final Map<String,String> options; final ValueChanged<String?> onChanged;
  @override Widget build(BuildContext c) => Padding(padding:const EdgeInsets.only(bottom:12),child:DropdownButtonFormField<String>(
    value: value!=null && options.containsKey(value) ? value : null,
    isExpanded:true,
    dropdownColor:Colors.white,
    borderRadius:BorderRadius.circular(10),
    elevation:3,
    icon: const Icon(Icons.keyboard_arrow_down_rounded,color:Color(0xFF5A7196)),
    decoration:InputDecoration(labelText:label,labelStyle:const TextStyle(color:Color(0xFF274B79),fontSize:13),isDense:true,filled:true,fillColor:Colors.white,contentPadding:const EdgeInsets.symmetric(horizontal:13,vertical:15),border:OutlineInputBorder(borderRadius:BorderRadius.circular(10))),
    items:options.entries.map((e)=>DropdownMenuItem(value:e.key,child:Text(e.value,overflow:TextOverflow.ellipsis,style:const TextStyle(color:Color(0xFF00133A))))).toList(),
    onChanged:onChanged,
  ));
}

class _ToggleField extends StatelessWidget{
  const _ToggleField({required this.label,required this.value,required this.onChanged});
  final String label; final bool value; final ValueChanged<bool> onChanged;
  @override Widget build(BuildContext c) => Padding(padding:const EdgeInsets.only(bottom:4),child:SwitchListTile(contentPadding:EdgeInsets.zero,value:value,onChanged:onChanged,activeColor:_blue,title:Text(label,style:const TextStyle(fontWeight:FontWeight.w600,fontSize:13.5))));
}

class MediaStep extends StatefulWidget{const MediaStep({super.key,required this.service,required this.productId,required this.existing});final ProductsService service;final int productId;final String existing;@override State<MediaStep>createState()=>_MediaStepState();}
class _MediaStepState extends State<MediaStep>{
  final picker=ImagePicker();
  final files=<XFile>[];
  final lowQuality=<String,bool>{};
  bool busy=false;
  Future<void> add() async {
    final x=await picker.pickImage(source:ImageSource.gallery,imageQuality:90);
    if(x==null)return;
    setState(()=>busy=true);
    try{
      final bytes=await File(x.path).readAsBytes();
      var poor=bytes.length<25000;
      try{
        final codec=await ui.instantiateImageCodec(bytes);
        final frame=await codec.getNextFrame();
        if(frame.image.width<640||frame.image.height<640)poor=true;
      }catch(_){}
      await widget.service.uploadMedia(widget.productId,x.path);
      if(!mounted)return;
      setState((){files.add(x);lowQuality[x.path]=poor;});
    }finally{if(mounted)setState(()=>busy=false);}
  }
  @override Widget build(BuildContext c){
    final hasLowQuality=lowQuality.values.any((v)=>v);
    return Column(crossAxisAlignment:CrossAxisAlignment.start,children:[
      const Text('Média',style:TextStyle(fontSize:22,fontWeight:FontWeight.w800)),
      const Text('Ajoutez des photos nettes et bien éclairées : de bonnes photos attirent davantage de clients.'),
      const SizedBox(height:12),
      if(hasLowQuality)Container(margin:const EdgeInsets.only(bottom:12),padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFFFF4E5),borderRadius:BorderRadius.circular(10),border:Border.all(color:const Color(0xFFF0B429))),child:const Row(children:[Icon(Icons.warning_amber_rounded,color:Color(0xFFB07500)),SizedBox(width:8),Expanded(child:Text('Qualité insuffisante détectée sur au moins une photo (marquée ci-dessous). Remplacez-la par une image plus nette et bien éclairée pour attirer davantage de clients.',style:TextStyle(color:Color(0xFF7A4E00),fontSize:12.5)))])),
      Wrap(spacing:10,runSpacing:10,children:[
        if(widget.existing.isNotEmpty)Image.network(widget.existing,width:110,height:110,fit:BoxFit.cover,errorBuilder:(_,__,___)=>const SizedBox()),
        ...files.map((f)=>SizedBox(width:110,height:110,child:Stack(children:[
          ClipRRect(borderRadius:BorderRadius.circular(8),child:Image.file(File(f.path),width:110,height:110,fit:BoxFit.cover)),
          if(lowQuality[f.path]==true)Positioned(bottom:4,left:4,right:4,child:Container(padding:const EdgeInsets.symmetric(horizontal:6,vertical:3),decoration:BoxDecoration(color:const Color(0xFFB03A2E),borderRadius:BorderRadius.circular(6)),child:const Text('Qualité faible',textAlign:TextAlign.center,style:TextStyle(color:Colors.white,fontSize:9,fontWeight:FontWeight.w700)))),
        ]))),
        InkWell(onTap:busy?null:add,child:Container(width:110,height:110,decoration:BoxDecoration(border:Border.all(color:_blue),borderRadius:BorderRadius.circular(12)),child:Column(mainAxisAlignment:MainAxisAlignment.center,children:[busy?const SizedBox(width:20,height:20,child:CircularProgressIndicator(strokeWidth:2)):const Icon(Icons.add_circle_outline,color:_blue),const SizedBox(height:4),const Text('Ajouter',style:TextStyle(color:_blue))]))),
      ]),
    ]);
  }
}

class ProductRecapScreen extends StatefulWidget{const ProductRecapScreen({super.key,required this.service,required this.productId,required this.session,required this.chrome});final ProductsService service;final int productId;final CaptureSessionData session;final Widget Function({required Widget child,required int tab}) chrome;@override State<ProductRecapScreen>createState()=>_ProductRecapScreenState();}class _ProductRecapScreenState extends State<ProductRecapScreen>{Map<String,dynamic>? p;String? error;bool ok=false,publishing=false;@override void initState(){super.initState();_load();}void _load(){setState(()=>error=null);widget.service.product(widget.productId).then((j){if(mounted)setState(()=>p=Map<String,dynamic>.from((j['product'] as Map?) ?? const {}));}).catchError((e){if(mounted)setState(()=>error='$e');});}Future<void>pub()async{if(!ok)return;setState(()=>publishing=true);try{await widget.service.publish(widget.productId);if(!mounted)return;ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content:Text('Produit publié avec succès.')));Navigator.popUntil(context,(r)=>r.isFirst);}catch(e){ScaffoldMessenger.of(context).showSnackBar(SnackBar(content:Text('$e')));}finally{if(mounted)setState(()=>publishing=false);}}@override Widget build(BuildContext c)=>widget.chrome(tab:3,child:ListView(padding:const EdgeInsets.fromLTRB(16,16,16,110),children:[ProductHeader(user:const {}),const SizedBox(height:20),const Text('Récapitulatif produit',style:TextStyle(color:Colors.white,fontSize:30,fontWeight:FontWeight.w800)),const Text('Vérifiez toutes les informations avant la publication du produit.',style:TextStyle(color:_muted)),const SizedBox(height:12),if(error!=null)WhiteCard(child:Column(children:[Text('Impossible de charger le produit : $error',textAlign:TextAlign.center),const SizedBox(height:12),FilledButton(onPressed:_load,child:const Text('Réessayer'))]))else if(p==null)const Center(child:CircularProgressIndicator())else WhiteCard(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[const StepStrip(active:6),ProductIdentity(product:p!),const Divider(height:28),...['Informations','Prix & unité','Détails techniques','Logistique','Média'].asMap().entries.map((e)=>Padding(padding:const EdgeInsets.symmetric(vertical:8),child:ListTile(tileColor:const Color(0xFFF5F8FE),shape:RoundedRectangleBorder(borderRadius:BorderRadius.circular(12)),leading:CircleAvatar(backgroundColor:_blue,foregroundColor:Colors.white,child:Text('${e.key+1}')),title:Text(e.value,style:const TextStyle(fontWeight:FontWeight.w700)),trailing:const Icon(Icons.check_circle,color:Color(0xFF1DB66D))))),CheckboxListTile(contentPadding:EdgeInsets.zero,value:ok,onChanged:(v)=>setState(()=>ok=v??false),title:const Text('Je confirme l’exactitude des informations saisies')),FilledButton(style:FilledButton.styleFrom(backgroundColor:_orange,minimumSize:const Size.fromHeight(52)),onPressed:ok&&!publishing?pub:null,child:Text(publishing?'Publication...':'Publier le produit'))]))]));}

class ProductScaffold extends StatelessWidget {
  const ProductScaffold({
    super.key,
    required this.child,
    required this.tab,
    required this.initialUser,
    required this.onTab,
  });

  final Widget child;
  final int tab;
  final Map<String, dynamic> initialUser;
  final ValueChanged<int> onTab;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = (width / 472).clamp(.78, 1.08).toDouble();
    double sx(double value) => value * scale;
    final profile = CommercialProfile.fromJson(initialUser);
    final unread = (initialUser['unread_notifications'] as num?)?.toInt() ?? 0;

    return Scaffold(
      body: BrandBackground(
        child: SafeArea(
          bottom: false,
          child: Column(
            children: [
              Padding(
                padding: EdgeInsets.fromLTRB(sx(16), sx(8), sx(16), 0),
                child: CommercialClientsHeader(
                  profile: profile,
                  unreadNotifications: unread,
                  scale: scale,
                  onNotificationsTap: () => ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Notifications : écran prévu dans le prochain lot.')),
                  ),
                  onAvatarTap: () => ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Profil commercial')),
                  ),
                ),
              ),
              Expanded(
                child: _ProductChromeScope(
                  user: initialUser,
                  scale: scale,
                  child: child,
                ),
              ),
            ],
          ),
        ),
      ),
      extendBody: true,
      bottomNavigationBar: CommercialBottomNavigation(
        scale: scale,
        currentIndex: tab,
        onTap: onTab,
      ),
    );
  }
}

class _ProductChromeScope extends InheritedWidget {
  const _ProductChromeScope({required this.user, required this.scale, required super.child});
  final Map<String, dynamic> user;
  final double scale;

  static _ProductChromeScope? maybeOf(BuildContext context) =>
      context.dependOnInheritedWidgetOfExactType<_ProductChromeScope>();

  @override
  bool updateShouldNotify(_ProductChromeScope oldWidget) =>
      oldWidget.user != user || oldWidget.scale != scale;
}
class ProductHeader extends StatelessWidget {
  const ProductHeader({super.key, required this.user});
  final Map<String, dynamic> user;

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
class SearchRow extends StatelessWidget{const SearchRow({super.key,required this.controller,required this.hint,required this.onChanged});final TextEditingController controller;final String hint;final ValueChanged<String> onChanged;@override Widget build(BuildContext c)=>Row(children:[Expanded(child:TextField(controller:controller,onChanged:onChanged,style:const TextStyle(color:Colors.white),decoration:InputDecoration(prefixIcon:const Icon(Icons.search,color:_muted),hintText:hint,hintStyle:const TextStyle(color:_muted),filled:true,fillColor:const Color(0xFF0A2955),border:OutlineInputBorder(borderSide:const BorderSide(color:_line),borderRadius:BorderRadius.circular(13))))),const SizedBox(width:10),OutlinedButton.icon(style:OutlinedButton.styleFrom(foregroundColor:Colors.white,side:const BorderSide(color:_line),padding:const EdgeInsets.symmetric(vertical:17,horizontal:14)),onPressed:(){},icon:const Icon(Icons.tune),label:const Text('Filtres'))]);}
class WhiteCard extends StatelessWidget{const WhiteCard({super.key,required this.child});final Widget child;@override Widget build(BuildContext c)=>Container(padding:const EdgeInsets.all(16),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(18)),child:DefaultTextStyle(style:const TextStyle(color:Color(0xFF00133A),fontSize:14),child:child));}
class InfoBar extends StatelessWidget{const InfoBar({super.key,required this.text,this.light=false});final String text;final bool light;@override Widget build(BuildContext c)=>Container(padding:const EdgeInsets.all(13),decoration:BoxDecoration(color:light?const Color(0xFFEAF3FF):const Color(0xFF082956),borderRadius:BorderRadius.circular(12),border:Border.all(color:light?const Color(0xFFD8E7FF):_line)),child:Row(crossAxisAlignment:CrossAxisAlignment.start,children:[Icon(Icons.info_outline,color:light?_blue:const Color(0xFF2B91FF)),const SizedBox(width:10),Expanded(child:Text(text,style:TextStyle(color:light?const Color(0xFF16458D):Colors.white70,height:1.35)))]));}
class SelectionBar extends StatelessWidget{const SelectionBar({super.key,required this.text});final String text;@override Widget build(BuildContext c)=>Container(padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:const Color(0xFF062654),borderRadius:BorderRadius.circular(12),border:Border.all(color:const Color(0xFF0571E8))),child:Row(children:[const Icon(Icons.storefront_outlined,color:Color(0xFF0A8EFF)),const SizedBox(width:10),Expanded(child:Text(text,style:const TextStyle(color:Colors.white,fontWeight:FontWeight.w600)))]));}
class ShopChoiceCard extends StatelessWidget {
  const ShopChoiceCard({
    super.key,
    required this.shop,
    required this.selected,
    required this.onTap,
  });
  final CommercialShopOption shop;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final normalized = shop.status.toLowerCase();
    final isOnline = normalized == 'online' || normalized == 'active';
    final isSuspended = normalized == 'suspended';
    final badgeBg = isOnline
        ? const Color(0xFFDDF7E8)
        : isSuspended
            ? const Color(0xFFFDE3EA)
            : const Color(0xFFFFF3D6);
    final badgeFg = isOnline
        ? const Color(0xFF008B49)
        : isSuspended
            ? const Color(0xFFEA3151)
            : const Color(0xFFB66B00);
    final badgeText = isOnline ? 'En ligne' : isSuspended ? 'Suspendue' : 'En attente';

    return Padding(
      padding: const EdgeInsets.only(bottom: 9),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: selected ? _blue : const Color(0xFFE2E8F1), width: selected ? 2.2 : 1),
            boxShadow: selected
                ? [BoxShadow(color: _blue.withOpacity(.22), blurRadius: 10)]
                : null,
          ),
          child: Row(
            children: [
              SizedBox(
                width: 28,
                child: Radio<int>(
                  value: shop.id,
                  groupValue: selected ? shop.id : null,
                  onChanged: (_) => onTap(),
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  visualDensity: VisualDensity.compact,
                ),
              ),
              const SizedBox(width: 5),
              ClipRRect(
                borderRadius: BorderRadius.circular(9),
                child: Container(
                  width: 76,
                  height: 70,
                  color: const Color(0xFFEAF2FC),
                  child: shop.imageUrl?.isNotEmpty == true
                      ? Image.network(
                          shop.imageUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Icon(Icons.storefront, size: 36, color: Color(0xFF8299BB)),
                        )
                      : const Icon(Icons.storefront, size: 36, color: Color(0xFF8299BB)),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            shop.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF00133A)),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(color: badgeBg, borderRadius: BorderRadius.circular(14)),
                          child: Text(badgeText, style: TextStyle(color: badgeFg, fontSize: 10.5, fontWeight: FontWeight.w600)),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      shop.category.isEmpty ? 'Boutique OVANIE' : shop.category,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xFF34598C), fontSize: 12),
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        const Icon(Icons.location_on_outlined, color: Color(0xFF24569A), size: 15),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            shop.location,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Color(0xFF34598C), fontSize: 11.5),
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 10),
                    Text(
                      '${shop.productsCount} produits   •   ${shop.logistics}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Color(0xFF173A70), fontSize: 11.5, fontWeight: FontWeight.w600),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
class SessionCard extends StatelessWidget{const SessionCard({super.key,required this.data,required this.onTap});final CaptureSessionData data;final VoidCallback onTap;@override Widget build(BuildContext c)=>Padding(padding:const EdgeInsets.only(bottom:10),child:InkWell(onTap:onTap,child:Container(padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(14)),child:Row(children:[Container(width:68,height:68,decoration:BoxDecoration(color:const Color(0xFFE9F1FC),borderRadius:BorderRadius.circular(14)),child:const Icon(Icons.camera_alt_outlined,color:_blue,size:34)),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(data.name,style:const TextStyle(fontWeight:FontWeight.w800,fontSize:17,color:Color(0xFF00133A))),Text('${data.date} • ${data.shopName}',style:const TextStyle(color:Color(0xFF28548B))),const SizedBox(height:8),LinearProgressIndicator(value:data.progress,minHeight:6,borderRadius:BorderRadius.circular(10)),Text('${data.captured} capturés • ${data.completed} complétés • ${(data.progress*100).round()}%',style:const TextStyle(color:Color(0xFF315B8A),fontSize:12))])),const Icon(Icons.chevron_right,color:_blue)]))));}
class Field extends StatelessWidget{const Field({super.key,required this.label,required this.controller,this.maxLines=1,this.keyboard});final String label;final TextEditingController controller;final int maxLines;final TextInputType? keyboard;@override Widget build(BuildContext c)=>Padding(padding:const EdgeInsets.only(bottom:12),child:TextField(controller:controller,maxLines:maxLines,keyboardType:keyboard,style:const TextStyle(color:Color(0xFF00133A)),decoration:InputDecoration(labelText:label,labelStyle:const TextStyle(color:Color(0xFF274B79)),filled:true,fillColor:Colors.white,border:OutlineInputBorder(borderRadius:BorderRadius.circular(10)))));}
class DropdownField extends StatelessWidget {
  const DropdownField({
    super.key,
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });
  final String label;
  final int? value;
  final List<Map<String, dynamic>> items;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) => DropdownButtonFormField<int>(
        value: value,
        isExpanded: true,
        dropdownColor: Colors.white,
        borderRadius: BorderRadius.circular(10),
        elevation: 3,
        itemHeight: 42,
        menuMaxHeight: 320,
        icon: const Icon(Icons.keyboard_arrow_down_rounded, color: Color(0xFF5A7196), size: 20),
        style: const TextStyle(color: Color(0xFF00133A), fontSize: 14),
        decoration: InputDecoration(
          labelText: label,
          labelStyle: const TextStyle(color: Color(0xFF274B79), fontSize: 13),
          isDense: true,
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(horizontal: 13, vertical: 15),
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        ),
        items: items
            .map(
              (e) => DropdownMenuItem<int>(
                value: (e['id'] as num).toInt(),
                child: Text(
                  '${e['name']}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Color(0xFF00133A), fontSize: 14),
                ),
              ),
            )
            .toList(),
        onChanged: onChanged,
      );
}
class StepTitle extends StatelessWidget{const StepTitle({super.key,required this.n,required this.title});final int n;final String title;@override Widget build(BuildContext c)=>Padding(padding:const EdgeInsets.only(bottom:10,top:4),child:Row(children:[CircleAvatar(radius:14,backgroundColor:_blue,foregroundColor:Colors.white,child:Text('$n')),const SizedBox(width:10),Text(title,style:const TextStyle(fontSize:17,fontWeight:FontWeight.w800))]));}
class Metric extends StatelessWidget{const Metric({super.key,required this.n,required this.label});final int n;final String label;@override Widget build(BuildContext c)=>Column(children:[Text('$n',style:const TextStyle(fontSize:22,fontWeight:FontWeight.w800,color:Color(0xFF00133A))),Text(label,textAlign:TextAlign.center,style:const TextStyle(color:Color(0xFF315B8A),fontSize:11))]);}
class ProductRow extends StatelessWidget{const ProductRow({super.key,required this.p,required this.onTap,this.action='Voir'});final CapturedProductData p;final VoidCallback onTap;final String action;@override Widget build(BuildContext c)=>Padding(padding:const EdgeInsets.only(bottom:8),child:InkWell(onTap:onTap,child:Container(padding:const EdgeInsets.all(9),decoration:BoxDecoration(color:const Color(0xFFF8FAFD),border:Border.all(color:const Color(0xFFD6E0EC)),borderRadius:BorderRadius.circular(10)),child:Row(children:[Container(width:62,height:62,color:const Color(0xFFEAF2FC),child:p.imageUrl.isNotEmpty?Image.network(p.imageUrl,fit:BoxFit.cover,errorBuilder:(_,__,___)=>const Icon(Icons.inventory_2_outlined)):const Icon(Icons.inventory_2_outlined)),const SizedBox(width:10),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(p.name,style:const TextStyle(fontWeight:FontWeight.w800,color:Color(0xFF00133A))),Text('Réf. ${p.reference} • ${p.capturedAt}',style:const TextStyle(fontSize:12,color:Color(0xFF315B8A))),Text('${p.category} • ${p.subcategory}',style:const TextStyle(fontSize:12,color:Color(0xFF315B8A)))])),OutlinedButton(onPressed:onTap,child:Text(action))]))));}
class InfoTile extends StatelessWidget{const InfoTile({super.key,required this.title,required this.value});final String title,value;@override Widget build(BuildContext c)=>ListTile(leading:const Icon(Icons.sell_outlined,color:_blue),title:Text(title,style:const TextStyle(color:Color(0xFF315B8A))),subtitle:Text(value,style:const TextStyle(color:Color(0xFF00133A),fontWeight:FontWeight.w700)));}
class StepStrip extends StatelessWidget{const StepStrip({super.key,required this.active});final int active;@override Widget build(BuildContext c){final labels=['Informations','Prix & unité','Détails techniques','Logistique','Média',if(active==6)'Récapitulatif'];return Row(children:[for(int i=0;i<labels.length;i++)Expanded(child:Column(children:[CircleAvatar(radius:16,backgroundColor:i+1<=active?_blue:const Color(0xFFE2E8F1),foregroundColor:i+1<=active?Colors.white:const Color(0xFF526985),child:Icon(i+1<active?Icons.check:Icons.circle,size:i+1<active?17:7)),const SizedBox(height:4),Text(labels[i],textAlign:TextAlign.center,style:TextStyle(fontSize:10,color:i+1==active?_blue:const Color(0xFF314A70),fontWeight:i+1==active?FontWeight.w800:FontWeight.w500))]))]);}}
class ProductIdentity extends StatelessWidget{const ProductIdentity({super.key,required this.product});final Map<String,dynamic> product;@override Widget build(BuildContext c)=>Container(padding:const EdgeInsets.all(12),decoration:BoxDecoration(color:const Color(0xFFF7FAFE),borderRadius:BorderRadius.circular(12),border:Border.all(color:const Color(0xFFE0E8F2))),child:Row(children:[Container(width:92,height:92,color:const Color(0xFFE8EFF8),child:'${product['image_url']??''}'.isNotEmpty?Image.network('${product['image_url']}',fit:BoxFit.cover,errorBuilder:(_,__,___)=>const Icon(Icons.inventory_2_outlined)):const Icon(Icons.inventory_2_outlined)),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${product['name']??'Produit à compléter'}',style:const TextStyle(fontSize:18,fontWeight:FontWeight.w800)),Text('Réf. provisoire : ${product['reference']??''}',style:const TextStyle(color:Color(0xFF315B8A))),const SizedBox(height:6),Wrap(spacing:8,children:[Chip(label:Text('${product['category']??''}')),Chip(label:Text('${product['subcategory']??''}'))])]))]));}
class EmptyCard extends StatelessWidget{const EmptyCard({super.key,required this.text});final String text;@override Widget build(BuildContext c)=>Container(padding:const EdgeInsets.all(24),decoration:BoxDecoration(color:Colors.white,borderRadius:BorderRadius.circular(14)),child:Center(child:Text(text,style:const TextStyle(color:Color(0xFF315B8A)))));}

extension _FirstOrNull<E> on Iterable<E>{E? get firstOrNull=>isEmpty?null:first;}
