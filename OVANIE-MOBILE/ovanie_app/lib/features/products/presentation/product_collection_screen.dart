import 'package:flutter/material.dart';

import '../../../app/theme.dart';
import '../../../core/layout/responsive.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/api_error_card.dart';
import '../../../shared/widgets/product_card.dart';
import '../../home/data/marketplace_repository.dart';
import '../domain/product_model.dart';

enum ProductCollectionType {
  latest,
  bestSellers,
  recommendations,
}

class ProductCollectionScreen extends StatefulWidget {
  final String title;
  final ProductCollectionType type;

  const ProductCollectionScreen({
    super.key,
    required this.title,
    required this.type,
  });

  @override
  State<ProductCollectionScreen> createState() => _ProductCollectionScreenState();
}

class _ProductCollectionScreenState extends State<ProductCollectionScreen> {
  final MarketplaceRepository _repository = const MarketplaceRepository();
  List<ProductModel> _products = const [];
  bool _loading = true;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      late final List<ProductModel> products;
      switch (widget.type) {
        case ProductCollectionType.latest:
          products = await _repository.getLatestProducts(limit: 60);
          break;
        case ProductCollectionType.bestSellers:
          products = await _repository.getBestSellers(limit: 60);
          break;
        case ProductCollectionType.recommendations:
          products = await _repository.getRecommendations(limit: 60);
          break;
      }

      if (!mounted) return;
      setState(() {
        _products = products;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: OvanieColors.background,
      appBar: AppBar(
        title: Text(
          widget.title,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        color: OvanieColors.orange,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return ListView(
        physics: AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(height: 260),
          Center(child: CircularProgressIndicator(color: OvanieColors.orange)),
        ],
      );
    }

    if (_error != null) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          ApiErrorCard(
            message: ApiClient.friendlyError(_error!),
            onRetry: _load,
          ),
        ],
      );
    }

    if (_products.isEmpty) {
      return ListView(
        physics: AlwaysScrollableScrollPhysics(),
        children: [
          SizedBox(height: 240),
          Center(
            child: Text(
              'Aucun produit disponible pour le moment.',
              style: TextStyle(color: OvanieColors.muted),
            ),
          ),
        ],
      );
    }

    return LayoutBuilder(
      builder: (context, viewport) {
        final availableWidth = viewport.maxWidth;
        final gridPadding =
            OvanieResponsive.horizontalPaddingForWidth(availableWidth);
        final gridColumns =
            OvanieResponsive.productGridColumnsForWidth(availableWidth);
        final gridTileWidth = OvanieResponsive.productTileWidthForWidth(
          availableWidth,
          gridColumns,
        );
        final gridExtent =
            ProductCard.extentForWidth(context, width: gridTileWidth);

        return GridView.builder(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.fromLTRB(gridPadding, 16, gridPadding, 28),
          itemCount: _products.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: gridColumns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            mainAxisExtent: gridExtent,
          ),
          itemBuilder: (context, index) =>
              ProductCard(product: _products[index]),
        );
      },
    );
  }
}
