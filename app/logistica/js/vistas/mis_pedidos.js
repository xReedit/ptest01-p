import{cargarHistorias as s}from"../historia.js?v=20261015g";import{api as i}from"../api.js?v=20261015g";import{esc as o,aviso as r,tarjeta as n,volver as c,confirmar as l,fecha as p}from"../ui.js?v=20261015g";import{vigente as v,recargar as h}from"../router.js?v=20261015g";const u={enviado:["Enviado","borrador"],visto:["La central lo vio","cerrado"],atendido:["Preparando","cerrado"],despachado:["En camino","cerrado"],recibido:["Recibido","aprobado"],cancelado:["Cancelado","anulado"]},m=a=>Number(a).toLocaleString("es-PE",{maximumFractionDigits:2});async function a(e,a,t){var d=await i("pedidos.php?op=mis_pedidos");v(t)&&(e.innerHTML=n({titulo:"Mis pedidos",ayuda:"Los últimos 30 pedidos de tu sede a la central y en qué va cada uno.",acciones:c()+'<a class="btn btn-sm btn-success" href="#" data-ir="pedir"><i class="fa fa-plus"></i>Nuevo pedido</a>',cuerpo:d.length?`
			<div class="inv-table-wrap"><table class="inv-table">
				<thead><tr><th>N°</th><th>Fecha</th><th>Productos</th><th>Estado</th><th></th></tr></thead>
				<tbody>${d.map(a=>{var[t,e]=u[a.estado]||[a.estado,""];return`<tr>
						<td>${a.id}</td>
						<td>${o(p(a.fecha))}${a.nota?`<span class="inv-sub">${o(a.nota)}</span>`:""}</td>
						<td>${a.productos}</td>
						<td><span class="inv-estado ${e}">${o(t)}</span></td>
						<td class="inv-acciones"><span class="inv-link" data-ver="${a.id}"><i class="fa fa-eye"></i>Ver</span>
							${"enviado"===a.estado?`<span class="inv-link inv-link-rojo" data-cancelar="${a.id}"><i class="fa fa-times"></i>Cancelar</span>`:""}</td>
					</tr>`}).join("")}</tbody>
			</table></div>
			<dialog class="lg-dlg" id="mp_dlg"></dialog>`:'<p class="inv-empty"><i class="fa fa-shopping-basket"></i>Aún no has hecho pedidos. Presiona <b>Nuevo pedido</b>.</p>'}),e.querySelector(".xContentCard").addEventListener("click",async a=>{var t=a.target.closest("[data-ver]"),t=(t&&!async function(a,t){try{var e=await i("pedidos.php?op=detalle&id="+t);const d=a.querySelector("#mp_dlg");d.innerHTML=`
			<div class="xdlg-head"><h3>Pedido N° ${t}</h3></div>
			<div class="xdlg-body"><div class="inv-table-wrap"><table class="inv-table">
				<thead><tr><th>Producto</th><th style="text-align:right">Pediste</th><th style="text-align:right">Te envían</th></tr></thead>
				<tbody>${e.map(a=>`<tr><td>${o(a.descripcion)}</td>
					<td style="text-align:right">${m(a.cantidad_solicitada)} <span class="inv-muted">${o(a.unidad_medida||"und")}</span></td>
					<td style="text-align:right">${Number(a.cantidad_despachada)?m(a.cantidad_despachada):'<span class="inv-muted">—</span>'}</td></tr>`).join("")}</tbody>
			</table></div><div data-historia="pedido:${t}"></div></div>
			<div class="xdlg-foot"><button class="btn btn-sm btn-light" id="mp_cerrar"><i class="fa fa-times"></i>Cerrar</button></div>`,d.querySelector("#mp_cerrar").addEventListener("click",()=>d.close()),d.showModal(),s(d)}catch(a){r(a.message,"error")}}(e,Number(t.dataset.ver)),a.target.closest("[data-cancelar]"));t&&!async function(a){var t=await l({titulo:`¿Cancelar el pedido N° ${a}?`,boton:"Sí, cancelar pedido",textoHtml:'<p style="margin:0">La central ya no lo verá. Si necesitas esos productos, haz un pedido nuevo.</p>'});if(t)try{await i("pedidos.php?op=cancelar",{id:a}),r(`Pedido N° ${a} cancelado.`),h()}catch(a){r(a.message,"error")}}(Number(t.dataset.cancelar))}))}export{a as render};