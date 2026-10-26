import{api as g}from"../api.js?v=20261026a";import{esc as _,aviso as h,autocompletar as y}from"../ui.js?v=20261026a";const A=["CAJA","PAQUETE","SACO","FARDO","DOCENA","BOLSA","BIDON","JABA","SIXPACK","BALDE","GALONERA"],E=a=>String(a??"").trim().toUpperCase(),x=a=>Number(a).toString();function w(a){return(a||"").split(";").filter(Boolean).map(a=>{var[a,e]=a.split("|");return{nombre:a,factor:x(e)}})}function a(n,a,l,r){var e,s,i=l?w(l.presentaciones):[];n.innerHTML=`
		<div class="xdlg-head">
			<h3>${l?"Editar producto":"Nuevo producto"}</h3>
			<p>${l?"Los cambios se aplican también en todas las sedes. El precio y el stock de cada sede no cambian.":"Se creará también en todas las sedes que participan."}</p>
		</div>
		<div class="xdlg-body">
			<p class="inv-titulo-sec" style="margin-top:0"><i class="fa fa-tag"></i> 1. Datos del producto</p>
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">Nombre</span><input class="inv-input inv-w100" id="f_desc" maxlength="150" value="${_(l?l.descripcion:"")}" placeholder="Ej: INCA KOLA 500ML"></div></div>
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">Familia</span><input class="inv-input inv-w100" id="f_fam" maxlength="80" value="${_(l?l.familia:"")}" placeholder="Escribe o elige: BEBIDAS, ABARROTES…"></div>
				<span class="inv-sub" id="f_fam_nota"></span></div>
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">¿En qué unidad se cuenta en el almacén?</span><input class="inv-input inv-w100" id="f_und" maxlength="50" value="${_(l&&(e=l.idunidad_kardex,s=a.unidades.find(a=>Number(a.idunidad_medida)===Number(e)),s)?s.unidad:"")}" placeholder="Vacío = por unidad (botella, lata, pieza). Ej: KILOGRAMOS, LITROS"></div>
				<span class="inv-sub" id="f_und_nota"></span></div>
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">Precio de venta (S/)</span><input class="inv-input" id="f_precio" type="number" min="0" step="0.01" inputmode="decimal" style="width:140px"
					value="${_(l?Number(l.precio_venta||0):"")}" placeholder="0.00"></div>
				<span class="inv-sub">Es el precio con el que llega a cada sede. Después cada sede puede cambiar el suyo.</span></div>
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">Código de barras (opcional)</span><input class="inv-input inv-w100" id="f_cod" maxlength="255" value="${_(l?l.codigo_barra:"")}"></div></div>

			<p class="inv-titulo-sec"><i class="fa fa-cube"></i> 2. ¿Cómo lo compras? <span class="inv-ayuda">(opcional)</span></p>
			<p class="inv-ayuda" style="margin:0 0 8px">Ej: <b>CAJA</b> que trae <b>12</b>. Así pides 5 cajas y el sistema sabe que son 60.</p>
			<div id="f_pres"></div>
			<button type="button" class="btn btn-sm btn-light" id="f_pres_add"><i class="fa fa-plus"></i>Agregar forma de compra</button>

			<p class="inv-titulo-sec"><i class="fa fa-truck"></i> 3. ¿A quién se lo compras? <span class="inv-ayuda">(opcional)</span></p>
			<div class="inv-form-row"><div class="inv-field"><span class="inv-label">Proveedor habitual</span><input class="inv-input inv-w100" id="f_prov" maxlength="150" value="${_(l?l.proveedor:"")}" placeholder="Escribe o elige el proveedor"></div>
				<span class="inv-sub" id="f_prov_nota"></span></div>

			<p class="inv-ayuda" style="margin:12px 0 0"><i class="fa fa-pie-chart"></i> ¿En las sedes se vende por porciones (ej. 1 bote = 20 porciones)?
				Se configura en <b>Catálogo → Porciones</b>.${l&&l.porciones?` Este producto rinde <b>${x(l.porciones)}</b> porciones.`:""}</p>
		</div>
		<div class="xdlg-foot">
			<button class="btn btn-sm btn-light" id="f_cancelar"><i class="fa fa-times"></i>Cancelar</button>
			<button class="btn btn-sm btn-success" id="f_guardar"><i class="fa fa-check"></i>Guardar</button>
		</div>`;const t=a=>n.querySelector(a),o=t("#f_fam"),d=t("#f_und"),c=t("#f_prov"),p=t("#f_pres"),u=()=>(d.value.trim()||"unidades").toLowerCase(),v=a=>{var e;p.insertAdjacentHTML("beforeend",(a=a,e=u(),`
		<div class="lg-pres" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px">
			<input class="inv-input lg-pres-nombre" style="flex:1 1 140px;width:auto" maxlength="40" value="${_(a.nombre)}" placeholder="Ej: CAJA">
			<span class="inv-ayuda">trae</span>
			<input class="inv-input lg-pres-factor" style="width:80px" type="number" min="0.01" step="any" inputmode="decimal" value="${_(a.factor)}" placeholder="12">
			<span class="inv-ayuda lg-pres-und" style="min-width:70px">${_(e)}</span>
			<button type="button" class="btn btn-sm btn-light lg-pres-quitar" title="Quitar"><i class="fa fa-trash-o" style="margin:0"></i></button>
		</div>`)),y(p.lastElementChild.querySelector(".lg-pres-nombre"),A)},f=(i.forEach(v),t("#f_pres_add").addEventListener("click",()=>{v({nombre:"",factor:""}),p.lastElementChild.querySelector("input").focus()}),p.addEventListener("click",a=>{a=a.target.closest(".lg-pres-quitar");a&&a.closest(".lg-pres").remove()}),(a,e,s,i)=>{const n=E(a.value);e.textContent=n?s.some(a=>E(a)===n)?"✓ Ya existe: se usará ese.":"Nuevo: se creará al guardar.":i}),m=a.unidades.flatMap(a=>[a.unidad,a.abreviatura]).concat(["UND","UNIDAD","PIEZA"]),b=()=>{f(o,t("#f_fam_nota"),a.familias,""),f(d,t("#f_und_nota"),m,"Se contará por unidad."),f(c,t("#f_prov_nota"),a.proveedores,""),p.querySelectorAll(".lg-pres-und").forEach(a=>{a.textContent=u()})};y(o,a.familias),y(d,a.unidades.map(a=>a.unidad)),y(c,a.proveedores),[o,d,c].forEach(a=>a.addEventListener("input",b)),b(),t("#f_cancelar").addEventListener("click",()=>n.close()),t("#f_guardar").addEventListener("click",async a=>{var a=a.currentTarget,e=[...p.querySelectorAll(".lg-pres")].map(a=>({nombre:a.querySelector(".lg-pres-nombre").value,factor:Number(a.querySelector(".lg-pres-factor").value)})).filter(a=>""!==a.nombre.trim()),s=e.find(a=>!(0<a.factor));if(s)h(`Indica cuántas ${u()} trae ${E(s.nombre)}.`,"error");else{s={idproducto:l?Number(l.idproducto):void 0,descripcion:t("#f_desc").value,familia:o.value,unidad:d.value,codigo_barra:t("#f_cod").value,precio_venta:t("#f_precio").value,presentaciones:e,proveedor:c.value};a.disabled=!0;try{var i=await g("catalogo.php?op="+(l?"editar":"crear"),s);n.close(),r(i,!l)}catch(a){h(a.message,"error")}finally{a.disabled=!1}}}),n.showModal()}export{w as leerPresentaciones,a as abrirForm};