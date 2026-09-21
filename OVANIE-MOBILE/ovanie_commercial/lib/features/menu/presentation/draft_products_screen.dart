import 'package:flutter/material.dart';

import '../../../core/theme/ovanie_colors.dart';
import '../data/menu_service.dart';
import '../models/menu_data.dart';
import 'menu_shared.dart';

class CommercialDraftProductsScreen extends StatefulWidget {
  const CommercialDraftProductsScreen({
    super.key,
    required this.menuService,
    required this.initialUser,
    required this.initialProfile,
    required this.unreadNotifications,
  });

  final MenuService menuService;
  final Map<String, dynamic> initialUser;
  final MenuProfileData? initialProfile;
  final int unreadNotifications;

  @override
  State<CommercialDraftProductsScreen> createState() => _CommercialDraftProductsScreenState();
}

class _CommercialDraftProductsScreenState extends State<CommercialDraftProductsScreen> {
  final _search = TextEditingController();
  DraftProductsData? _data;
  bool _loading = true;
  String? _error;
  String _filter = 'all';

  @override void initState(){super.initState();_load();}
  @override void dispose(){_search.dispose();super.dispose();}

  Future<void> _load() async{
    if(mounted)setState((){_loading=true;_error=null;});
    try{final d=await widget.menuService.drafts();if(mounted)setState(()=>_data=d);}catch(e){if(mounted)setState(()=>_error=e.toString().replaceFirst('ApiException: ',''));}finally{if(mounted)setState(()=>_loading=false);}
  }

  List<DraftProductData> get _items{
    final q=_search.text.trim().toLowerCase();
    return (_data?.items??const <DraftProductData>[]).where((item){
      final text='${item.name} ${item.reference} ${item.category} ${item.subcategory} ${item.sessionName}'.toLowerCase();
      if(q.isNotEmpty&&!text.contains(q))return false;
      if(_filter=='complete')return item.completionStep<4;
      if(_filter=='ready')return item.almostReady;
      return true;
    }).toList(growable:false);
  }

  @override Widget build(BuildContext context){
    final s=menuScaleOf(context).call;
    final profile=menuHeaderProfile(initialUser:widget.initialUser,loaded:widget.initialProfile);
    return MenuPageScaffold(
      profile:profile,
      unreadNotifications:widget.unreadNotifications,
      body:RefreshIndicator(
        onRefresh:_load,
        child:ListView(
          padding:EdgeInsets.fromLTRB(s(14),s(10),s(14),s(102)),
          children:[
            MenuSearchBar(hint:'Rechercher un brouillon, un produit, une référence...',controller:_search,onChanged:(_)=>setState((){}),onFilterTap:(){}),
            SizedBox(height:s(12)),
            MenuPageTitle(
              title:'Produits en brouillon',
              subtitle:'Retrouvez les fiches non publiées et reprenez leur complétion.',
              onBack:()=>Navigator.pop(context),
              action:OutlinedButton.icon(
                onPressed:()=>menuTabNavigate(context,3),
                icon:Icon(Icons.add_rounded,size:s(21)),
                label:const Text('Nouvelle session'),
                style:OutlinedButton.styleFrom(foregroundColor:OvanieColors.orange,side:const BorderSide(color:OvanieColors.orange),padding:EdgeInsets.symmetric(horizontal:s(14),vertical:s(12))),
              ),
            ),
            SizedBox(height:s(10)),
            DarkMenuCard(
              padding:EdgeInsets.symmetric(horizontal:s(13),vertical:s(11)),
              child:Row(children:[Icon(Icons.storefront_outlined,color:Colors.white,size:s(21)),SizedBox(width:s(10)),Expanded(child:Text('Brouillons de vos boutiques OVANIE',style:TextStyle(color:Colors.white,fontSize:s(11.5)))),Container(padding:EdgeInsets.symmetric(horizontal:s(9),vertical:s(5)),decoration:BoxDecoration(color:const Color(0xFF073A73),borderRadius:BorderRadius.circular(s(8)),border:Border.all(color:const Color(0xFF157BDB))),child:Text('${_data?.all??0} brouillon${(_data?.all??0)>1?'s':''}',style:TextStyle(color:menuBlue,fontSize:s(9.2))))]),
            ),
            SizedBox(height:s(10)),
            Row(children:[
              Expanded(child:_DraftFilter(label:'Tous',count:_data?.all??0,selected:_filter=='all',onTap:()=>setState(()=>_filter='all'))),SizedBox(width:s(8)),
              Expanded(child:_DraftFilter(label:'À compléter',count:_data?.toComplete??0,selected:_filter=='complete',onTap:()=>setState(()=>_filter='complete'))),SizedBox(width:s(8)),
              Expanded(child:_DraftFilter(label:'Presque prêts',count:_data?.almostReady??0,selected:_filter=='ready',onTap:()=>setState(()=>_filter='ready'))),
            ]),
            SizedBox(height:s(12)),
            LoadingOrError(
              loading:_loading,
              error:_error,
              onRetry:_load,
              child:_items.isEmpty
                  ?DarkMenuCard(child:Padding(padding:EdgeInsets.symmetric(vertical:s(28)),child:Center(child:Text('Aucun produit en brouillon.',style:TextStyle(color:const Color(0xFFC5D0E1),fontSize:s(11))))))
                  :Column(children:[for(var i=0;i<_items.length;i++)...[_DraftCard(item:_items[i],onTap:()=>menuTabNavigate(context,3)),if(i!=_items.length-1)SizedBox(height:s(10))]]),
            ),
            SizedBox(height:s(12)),
            const MenuInfoStrip(text:'Astuce : reprenez d’abord les brouillons les plus avancés pour publier plus vite vos produits.'),
            SizedBox(height:s(10)),
            Row(children:[
              Expanded(child:OutlinedButton(onPressed:()=>Navigator.pop(context),style:OutlinedButton.styleFrom(foregroundColor:menuBlue,side:const BorderSide(color:menuBlue),padding:EdgeInsets.symmetric(vertical:s(13))),child:const Text('Retour'))),
              SizedBox(width:s(10)),
              Expanded(child:FilledButton(onPressed:()=>menuTabNavigate(context,3),style:FilledButton.styleFrom(backgroundColor:OvanieColors.orange,padding:EdgeInsets.symmetric(vertical:s(13))),child:const Text('Voir les sessions'))),
            ]),
          ],
        ),
      ),
    );
  }
}

class _DraftFilter extends StatelessWidget{const _DraftFilter({required this.label,required this.count,required this.selected,required this.onTap});final String label;final int count;final bool selected;final VoidCallback onTap;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return InkWell(onTap:onTap,borderRadius:BorderRadius.circular(s(18)),child:Container(height:s(42),decoration:BoxDecoration(color:selected?const Color(0xFF0C73E6):const Color(0xFF05244C),borderRadius:BorderRadius.circular(s(18)),border:Border.all(color:selected?const Color(0xFF1A99FF):const Color(0xFF385C85))),child:Row(mainAxisAlignment:MainAxisAlignment.center,children:[Flexible(child:Text(label,overflow:TextOverflow.ellipsis,style:TextStyle(color:Colors.white,fontSize:s(10.2)))),SizedBox(width:s(7)),Container(width:s(20),height:s(20),alignment:Alignment.center,decoration:BoxDecoration(color:selected?const Color(0xFF156DCA):const Color(0xFF314A71),shape:BoxShape.circle),child:Text('$count',style:TextStyle(color:Colors.white,fontSize:s(8.6),fontWeight:FontWeight.w800))) ])));}}

class _DraftCard extends StatelessWidget{
  const _DraftCard({required this.item,required this.onTap});final DraftProductData item;final VoidCallback onTap;
  @override Widget build(BuildContext context){final s=menuScaleOf(context).call;final ready=item.almostReady;return WhiteMenuCard(padding:EdgeInsets.all(s(12)),child:Column(children:[Row(crossAxisAlignment:CrossAxisAlignment.start,children:[_ProductImage(item:imageOrNull(item.imageUrl)),SizedBox(width:s(14)),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(item.name,maxLines:2,overflow:TextOverflow.ellipsis,style:TextStyle(color:const Color(0xFF071A43),fontSize:s(15),fontWeight:FontWeight.w800)),SizedBox(height:s(4)),Text('Réf. provisoire : ${item.reference}',style:TextStyle(color:const Color(0xFF6B7891),fontSize:s(10))),SizedBox(height:s(7)),Wrap(spacing:s(7),runSpacing:s(5),children:[_ProductTag(item.category),if(item.subcategory.isNotEmpty)_ProductTag(item.subcategory)]),SizedBox(height:s(8)),Text('Session : ${item.sessionName}',maxLines:1,overflow:TextOverflow.ellipsis,style:TextStyle(color:const Color(0xFF3A4C70),fontSize:s(10.1))),SizedBox(height:s(7)),Text('Modifié le ${menuDate(item.updatedAt)}',style:TextStyle(color:const Color(0xFF6D7990),fontSize:s(9.5)))])),SizedBox(width:s(9)),Column(crossAxisAlignment:CrossAxisAlignment.end,children:[Container(padding:EdgeInsets.symmetric(horizontal:s(9),vertical:s(5)),decoration:BoxDecoration(color:ready?const Color(0xFFE9F8EE):const Color(0xFFFFF3E9),borderRadius:BorderRadius.circular(s(9)),border:Border.all(color:ready?const Color(0xFF54B875):const Color(0xFFFFAD63))),child:Text(ready?'Prêt à publier':'Brouillon',style:TextStyle(color:ready?const Color(0xFF16934C):const Color(0xFFE86A00),fontSize:s(9.3)))),SizedBox(height:s(10)),Row(children:[Icon(Icons.cloud_done_rounded,color:const Color(0xFF0B66D2),size:s(18)),SizedBox(width:s(5)),Text(item.synced?'Synchronisé':'En attente',style:TextStyle(color:const Color(0xFF0B66D2),fontSize:s(9.2)))]),SizedBox(height:s(27)),OutlinedButton(onPressed:onTap,style:OutlinedButton.styleFrom(foregroundColor:const Color(0xFF085BE6),side:const BorderSide(color:Color(0xFF085BE6))),child:Text(ready?'Finaliser':'Continuer'))])]),SizedBox(height:s(12)),Row(children:[Text('${item.completionStep.clamp(0,5)}/5 étapes complétées',style:TextStyle(color:const Color(0xFF263A5B),fontSize:s(9.8))),SizedBox(width:s(10)),Expanded(child:LinearProgressIndicator(value:item.progress,minHeight:s(5),borderRadius:BorderRadius.circular(s(6)),backgroundColor:const Color(0xFFDCE4F0),valueColor:const AlwaysStoppedAnimation(Color(0xFF0A65E9)))),SizedBox(width:s(8)),Text('${(item.progress*100).round()}%',style:TextStyle(color:const Color(0xFF263A5B),fontSize:s(10)))]) ]));}
}

String? imageOrNull(String? url)=>url==null||url.trim().isEmpty?null:url;
class _ProductImage extends StatelessWidget{const _ProductImage({required this.item});final String? item;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return ClipRRect(borderRadius:BorderRadius.circular(s(8)),child:SizedBox(width:s(105),height:s(120),child:item==null?Container(color:const Color(0xFFF0F3F8),child:Icon(Icons.inventory_2_outlined,color:const Color(0xFF7991B1),size:s(38))):Image.network(item!,fit:BoxFit.contain,errorBuilder:(_,__,___)=>Container(color:const Color(0xFFF0F3F8),child:Icon(Icons.inventory_2_outlined,color:const Color(0xFF7991B1),size:s(38))))));}}
class _ProductTag extends StatelessWidget{const _ProductTag(this.text);final String text;@override Widget build(BuildContext context){final s=menuScaleOf(context).call;return Container(padding:EdgeInsets.symmetric(horizontal:s(8),vertical:s(4)),decoration:BoxDecoration(color:const Color(0xFFEAF2FF),borderRadius:BorderRadius.circular(s(7))),child:Text(text,style:TextStyle(color:const Color(0xFF0B55B9),fontSize:s(8.9))));}}
