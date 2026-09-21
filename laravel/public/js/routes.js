export const apiRoutes = {
    products: '/api/products',
    productDetail: id => `/api/products/${id}`,
    myProducts: '/api/products/my',
    productImages: '/api/product-images',

    shops: '/api/shops',
    shopDetail: id => `/api/shops/${id}`,
    myShops: '/api/shops/my',

    cart: '/cart',
    cartAdd: id => `/cart/add/${id}`,
    cartRemove: id => `/cart/${id}/remove`,
    cartClear: '/cart/clear',
    cartCount: '/cart/count',
    Scart: '/cart-data',


};
