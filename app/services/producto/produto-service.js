class ProductoService {
    constructor() {
        this.httpFechtApi = new httpFecht();
    }

    async calcCostoConversion(idproducto, stock_actual) {
        try {
            const rpt = await this.httpFechtApi.axiosExecuteJSON({
                type: 'POST',
                url: '../services/producto/producto-service.php',
                data: {
                    op: 'calc-costo-conversion',
                    idproducto: idproducto,
                    stock_actual: stock_actual
                }
            });
            console.log('rpt', rpt);
            return rpt;
        } catch (error) {
            console.error('Error:', error);
            // throw error;
        }
    }

    calcCostoProductoReceta(producto, cantidad) {
        
    }
}

// Exporta una instancia de la clasea
// export const productoService = new ProductoService();