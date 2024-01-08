!function(e,t){"function"==typeof define&&define.amd?define(t):"object"==typeof exports&&"string"!=typeof exports.nodeName?module.exports=t():e.Croppie=t()}("undefined"!=typeof self?self:this,function(){function n(e,t){return function(){e.apply(t,arguments)}}function e(e){if("object"!=typeof this)throw new TypeError("Promises must be constructed via new");if("function"!=typeof e)throw new TypeError("not a function");this._state=null,this._value=null,this._deferreds=[],s(e,n(i,this),n(o,this))}function r(n){var i=this;return null===this._state?void this._deferreds.push(n):void u(function(){var e,t=i._state?n.onFulfilled:n.onRejected;if(null===t)(i._state?n.resolve:n.reject)(i._value);else{try{e=t(i._value)}catch(e){return void n.reject(e)}n.resolve(e)}})}function i(e){try{if(e===this)throw new TypeError("A promise cannot be resolved with itself.");if(e&&("object"==typeof e||"function"==typeof e)){var t=e.then;if("function"==typeof t)return void s(n(t,e),n(i,this),n(o,this))}this._state=!0,this._value=e,a.call(this)}catch(e){o.call(this,e)}}function o(e){this._state=!1,this._value=e,a.call(this)}function a(){for(var e=0,t=this._deferreds.length;e<t;e++)r.call(this,this._deferreds[e]);this._deferreds=null}function H(e,t,n,i){this.onFulfilled="function"==typeof e?e:null,this.onRejected="function"==typeof t?t:null,this.resolve=n,this.reject=i}function s(e,t,n){var i=!1;try{e(function(e){i||(i=!0,t(e))},function(e){i||(i=!0,n(e))})}catch(e){i||(i=!0,n(e))}}var t,l,u,O;function k(e,t){t=t||{bubbles:!1,cancelable:!1,detail:void 0};var n=document.createEvent("CustomEvent");return n.initCustomEvent(e,t.bubbles,t.cancelable,t.detail),n}"function"!=typeof Promise&&(t=this,l=setTimeout,u="function"==typeof setImmediate&&setImmediate||function(e){l(e,1)},O=Array.isArray||function(e){return"[object Array]"===Object.prototype.toString.call(e)},e.prototype.catch=function(e){return this.then(null,e)},e.prototype.then=function(n,i){var o=this;return new e(function(e,t){r.call(o,new H(n,i,e,t))})},e.all=function(){var s=Array.prototype.slice.call(1===arguments.length&&O(arguments[0])?arguments[0]:arguments);return new e(function(o,r){if(0===s.length)return o([]);for(var a=s.length,e=0;e<s.length;e++)!function t(n,e){try{if(e&&("object"==typeof e||"function"==typeof e)){var i=e.then;if("function"==typeof i)return i.call(e,function(e){t(n,e)},r)}s[n]=e,0==--a&&o(s)}catch(e){r(e)}}(e,s[e])})},e.resolve=function(t){return t&&"object"==typeof t&&t.constructor===e?t:new e(function(e){e(t)})},e.reject=function(n){return new e(function(e,t){t(n)})},e.race=function(o){return new e(function(e,t){for(var n=0,i=o.length;n<i;n++)o[n].then(e,t)})},e._setImmediateFn=function(e){u=e},"undefined"!=typeof module&&module.exports?module.exports=e:t.Promise||(t.Promise=e)),"undefined"!=typeof window&&"function"!=typeof window.CustomEvent&&(k.prototype=window.Event.prototype,window.CustomEvent=k),"undefined"==typeof HTMLCanvasElement||HTMLCanvasElement.prototype.toBlob||Object.defineProperty(HTMLCanvasElement.prototype,"toBlob",{value:function(e,t,n){for(var i=atob(this.toDataURL(t,n).split(",")[1]),o=i.length,r=new Uint8Array(o),a=0;a<o;a++)r[a]=i.charCodeAt(a);e(new Blob([r],{type:t||"image/png"}))}});var A=["Webkit","Moz","ms"],S="undefined"!=typeof document?document.createElement("div").style:{},j=[1,8,3,6],N=[2,7,4,5];function c(e){if(e in S)return e;for(var t=e[0].toUpperCase()+e.slice(1),n=A.length;n--;)if((e=A[n]+t)in S)return e}function h(e,t){for(var n in e=e||{},t)t[n]&&t[n].constructor&&t[n].constructor===Object?(e[n]=e[n]||{},h(e[n],t[n])):e[n]=t[n];return e}function p(e){return h({},e)}function d(e){var t;"createEvent"in document?((t=document.createEvent("HTMLEvents")).initEvent("change",!1,!0),e.dispatchEvent(t)):e.fireEvent("onchange")}function m(e,t,n){var i,o;for(o in"string"==typeof t&&(i=t,(t={})[i]=n),t)e.style[o]=t[o]}function f(e,t){e.classList?e.classList.add(t):e.className+=" "+t}function P(e,t){for(var n in t)e.setAttribute(n,t[n])}function g(e){return parseInt(e,10)}function T(e,t){var n=e.naturalWidth,i=e.naturalHeight,t=t||C(e);return t&&5<=t&&(e=n,n=i,i=e),{width:n,height:i}}var v=c("transform"),w=c("transformOrigin"),y=c("userSelect"),D={translate3d:{suffix:", 0px"},translate:{suffix:""}},b=function(e,t,n){this.x=parseFloat(e),this.y=parseFloat(t),this.scale=parseFloat(n)},x=(b.parse=function(e){return e.style?b.parse(e.style[v]):-1<e.indexOf("matrix")||-1<e.indexOf("none")?b.fromMatrix(e):b.fromString(e)},b.fromMatrix=function(e){var t=e.substring(7).split(",");return t.length&&"none"!==e||(t=[1,0,0,1,0,0]),new b(g(t[4]),g(t[5]),parseFloat(t[0]))},b.fromString=function(e){var e=e.split(") "),t=e[0].substring(Y.globals.translate.length+1).split(","),e=1<e.length?e[1].substring(6):1,n=1<t.length?t[0]:0,t=1<t.length?t[1]:0;return new b(n,t,e)},b.prototype.toString=function(){var e=D[Y.globals.translate].suffix||"";return Y.globals.translate+"("+this.x+"px, "+this.y+"px"+e+") scale("+this.scale+")"},function(e){e&&e.style[w]?(e=e.style[w].split(" "),this.x=parseFloat(e[0]),this.y=parseFloat(e[1])):(this.x=0,this.y=0)});function C(e){return e.exifdata&&e.exifdata.Orientation?g(e.exifdata.Orientation):1}function q(e,t,n){var i=t.width,o=t.height,r=e.getContext("2d");switch(e.width=t.width,e.height=t.height,r.save(),n){case 2:r.translate(i,0),r.scale(-1,1);break;case 3:r.translate(i,o),r.rotate(180*Math.PI/180);break;case 4:r.translate(0,o),r.scale(1,-1);break;case 5:e.width=o,e.height=i,r.rotate(90*Math.PI/180),r.scale(1,-1);break;case 6:e.width=o,e.height=i,r.rotate(90*Math.PI/180),r.translate(0,-o);break;case 7:e.width=o,e.height=i,r.rotate(-90*Math.PI/180),r.translate(-i,o),r.scale(1,-1);break;case 8:e.width=o,e.height=i,r.translate(0,i),r.rotate(-90*Math.PI/180)}r.drawImage(t,0,0,i,o),r.restore()}function K(){var e,t,n,i,o,r=this,a=r.options.viewport.type?"cr-vp-"+r.options.viewport.type:null;r.options.useCanvas=r.options.enableOrientation||E.call(r),r.data={},r.elements={},e=r.elements.boundary=document.createElement("div"),t=r.elements.viewport=document.createElement("div"),i=r.elements.img=document.createElement("img"),n=r.elements.overlay=document.createElement("div"),r.options.useCanvas?(r.elements.canvas=document.createElement("canvas"),r.elements.preview=r.elements.canvas):r.elements.preview=i,f(e,"cr-boundary"),e.setAttribute("aria-dropeffect","none"),i=r.options.boundary.width,o=r.options.boundary.height,m(e,{width:i+(isNaN(i)?"":"px"),height:o+(isNaN(o)?"":"px")}),f(t,"cr-viewport"),a&&f(t,a),m(t,{width:r.options.viewport.width+"px",height:r.options.viewport.height+"px"}),t.setAttribute("tabindex",0),f(r.elements.preview,"cr-image"),P(r.elements.preview,{alt:"preview","aria-grabbed":"false"}),f(n,"cr-overlay"),r.element.appendChild(e),e.appendChild(r.elements.preview),e.appendChild(t),e.appendChild(n),f(r.element,"croppie-container"),r.options.customClass&&f(r.element,r.options.customClass),function(){var s,l,u,r,c,h=this,t=!1;function p(e,t){var n=h.elements.preview.getBoundingClientRect(),i=c.y+t,o=c.x+e;h.options.enforceBoundary?(r.top>n.top+t&&r.bottom<n.bottom+t&&(c.y=i),r.left>n.left+e&&r.right<n.right+e&&(c.x=o)):(c.y=i,c.x=o)}function n(e){h.elements.preview.setAttribute("aria-grabbed",e),h.elements.boundary.setAttribute("aria-dropeffect",e?"move":"none")}function e(e){void 0!==e.button&&0!==e.button||(e.preventDefault(),t)||(t=!0,s=e.pageX,l=e.pageY,e.touches&&(e=e.touches[0],s=e.pageX,l=e.pageY),n(t),c=b.parse(h.elements.preview),window.addEventListener("mousemove",i),window.addEventListener("touchmove",i),window.addEventListener("mouseup",o),window.addEventListener("touchend",o),document.body.style[y]="none",r=h.elements.viewport.getBoundingClientRect())}function i(e){e.preventDefault();var t,n=e.pageX,i=e.pageY,o=(e.touches&&(n=(o=e.touches[0]).pageX,i=o.pageY),n-s),r=i-l,a={};if("touchmove"===e.type&&1<e.touches.length)return t=e.touches[0],e=e.touches[1],e=(t=Math.sqrt((t.pageX-e.pageX)*(t.pageX-e.pageX)+(t.pageY-e.pageY)*(t.pageY-e.pageY)))/(u=u||t/h._currentZoom),_.call(h,e),void d(h.elements.zoomer);p(o,r),a[v]=c.toString(),m(h.elements.preview,a),R.call(h),l=i,s=n}function o(){n(t=!1),window.removeEventListener("mousemove",i),window.removeEventListener("touchmove",i),window.removeEventListener("mouseup",o),window.removeEventListener("touchend",o),document.body.style[y]="",L.call(h),I.call(h),u=0}h.elements.overlay.addEventListener("mousedown",e),h.elements.viewport.addEventListener("keydown",function(e){var t,n,i;!e.shiftKey||38!==e.keyCode&&40!==e.keyCode?h.options.enableKeyMovement&&37<=e.keyCode&&e.keyCode<=40&&(e.preventDefault(),i=function(e){switch(e){case 37:return[1,0];case 38:return[0,1];case 39:return[-1,0];case 40:return[0,-1]}}(e.keyCode),c=b.parse(h.elements.preview),document.body.style[y]="none",r=h.elements.viewport.getBoundingClientRect(),n=(t=i)[0],t=i[1],i={},p(n,t),i[v]=c.toString(),m(h.elements.preview,i),R.call(h),document.body.style[y]="",L.call(h),I.call(h),u=0):(n=38===e.keyCode?parseFloat(h.elements.zoomer.value)+parseFloat(h.elements.zoomer.step):parseFloat(h.elements.zoomer.value)-parseFloat(h.elements.zoomer.step),h.setZoom(n))}),h.elements.overlay.addEventListener("touchstart",e)}.call(this),r.options.enableZoom&&function(){var n=this,e=n.elements.zoomerWrap=document.createElement("div"),t=n.elements.zoomer=document.createElement("input");function i(){!function(e){var t=this,n=e?e.transform:b.parse(t.elements.preview),i=e?e.viewportRect:t.elements.viewport.getBoundingClientRect(),o=e?e.origin:new x(t.elements.preview);function r(){var e={};e[v]=n.toString(),e[w]=o.toString(),m(t.elements.preview,e)}t._currentZoom=e?e.value:t._currentZoom,n.scale=t._currentZoom,t.elements.zoomer.setAttribute("aria-valuenow",t._currentZoom),r(),t.options.enforceBoundary&&(e=function(e){var t=this._currentZoom,n=e.width,e=e.height,i=this.elements.boundary.clientWidth/2,o=this.elements.boundary.clientHeight/2,r=this.elements.preview.getBoundingClientRect(),a=r.width,r=r.height,s=n/2,l=e/2,i=-1*(s/t-i),o=-1*(l/t-o),s=1/t*s,l=1/t*l;return{translate:{maxX:i,minX:i-(a*(1/t)-n*(1/t)),maxY:o,minY:o-(r*(1/t)-e*(1/t))},origin:{maxX:a*(1/t)-s,minX:s,maxY:r*(1/t)-l,minY:l}}}.call(t,i),i=e.translate,e=e.origin,n.x>=i.maxX&&(o.x=e.minX,n.x=i.maxX),n.x<=i.minX&&(o.x=e.maxX,n.x=i.minX),n.y>=i.maxY&&(o.y=e.minY,n.y=i.maxY),n.y<=i.minY)&&(o.y=e.maxY,n.y=i.minY);r(),Q.call(t),I.call(t)}.call(n,{value:parseFloat(t.value),origin:new x(n.elements.preview),viewportRect:n.elements.viewport.getBoundingClientRect(),transform:b.parse(n.elements.preview)})}function o(e){var t;if("ctrl"===n.options.mouseWheelZoom&&!0!==e.ctrlKey)return 0;t=e.wheelDelta?e.wheelDelta/1200:e.deltaY?e.deltaY/1060:e.detail?e.detail/-60:0,t=n._currentZoom+t*n._currentZoom,e.preventDefault(),_.call(n,t),i.call(n)}f(e,"cr-slider-wrap"),f(t,"cr-slider"),t.type="range",t.step="0.0001",t.value="1",t.style.display=n.options.showZoomer?"":"none",t.setAttribute("aria-label","zoom"),n.element.appendChild(e),e.appendChild(t),n._currentZoom=1,n.elements.zoomer.addEventListener("input",i),n.elements.zoomer.addEventListener("change",i),n.options.mouseWheelZoom&&(n.elements.boundary.addEventListener("mousewheel",o),n.elements.boundary.addEventListener("DOMMouseScroll",o))}.call(r),r.options.enableResize&&function(){var a,s,l,u,c,e,t,h=this,p=document.createElement("div"),n=!1,d=50;f(p,"cr-resizer"),m(p,{width:this.options.viewport.width+"px",height:this.options.viewport.height+"px"}),this.options.resizeControls.height&&(f(e=document.createElement("div"),"cr-resizer-vertical"),p.appendChild(e));this.options.resizeControls.width&&(f(t=document.createElement("div"),"cr-resizer-horisontal"),p.appendChild(t));function i(e){var t;void 0!==e.button&&0!==e.button||(e.preventDefault(),n)||(t=h.elements.overlay.getBoundingClientRect(),n=!0,s=e.pageX,l=e.pageY,a=-1!==e.currentTarget.className.indexOf("vertical")?"v":"h",u=t.width,c=t.height,e.touches&&(t=e.touches[0],s=t.pageX,l=t.pageY),window.addEventListener("mousemove",o),window.addEventListener("touchmove",o),window.addEventListener("mouseup",r),window.addEventListener("touchend",r),document.body.style[y]="none")}function o(e){var t=e.pageX,n=e.pageY,e=(e.preventDefault(),e.touches&&(t=(e=e.touches[0]).pageX,n=e.pageY),t-s),i=n-l,o=h.options.viewport.height+i,r=h.options.viewport.width+e;"v"===a&&d<=o&&o<=c?(m(p,{height:o+"px"}),h.options.boundary.height+=i,m(h.elements.boundary,{height:h.options.boundary.height+"px"}),h.options.viewport.height+=i,m(h.elements.viewport,{height:h.options.viewport.height+"px"})):"h"===a&&d<=r&&r<=u&&(m(p,{width:r+"px"}),h.options.boundary.width+=e,m(h.elements.boundary,{width:h.options.boundary.width+"px"}),h.options.viewport.width+=e,m(h.elements.viewport,{width:h.options.viewport.width+"px"})),R.call(h),z.call(h),L.call(h),I.call(h),l=n,s=t}function r(){n=!1,window.removeEventListener("mousemove",o),window.removeEventListener("touchmove",o),window.removeEventListener("mouseup",r),window.removeEventListener("touchend",r),document.body.style[y]=""}e&&(e.addEventListener("mousedown",i),e.addEventListener("touchstart",i));t&&(t.addEventListener("mousedown",i),t.addEventListener("touchstart",i));this.elements.boundary.appendChild(p)}.call(r)}function E(){return this.options.enableExif&&window.EXIF}function _(e){var t;this.options.enableZoom&&(t=this.elements.zoomer,e=W(e,4),t.value=Math.max(parseFloat(t.min),Math.min(parseFloat(t.max),e)).toString())}function L(e){var t,n,i,o=this,r=o._currentZoom,a=o.elements.preview.getBoundingClientRect(),s=o.elements.viewport.getBoundingClientRect(),l=b.parse(o.elements.preview.style[v]),u=new x(o.elements.preview),c=s.top-a.top+s.height/2,a=s.left-a.left+s.width/2,s={},h={},e=(e?(e=u.x,t=u.y,n=l.x,i=l.y,s.y=e,s.x=t,l.y=n,l.x=i):(s.y=c/r,s.x=a/r,h.y=(s.y-u.y)*(1-r),h.x=(s.x-u.x)*(1-r),l.x-=h.x,l.y-=h.y),{});e[w]=s.x+"px "+s.y+"px",e[v]=l.toString(),m(o.elements.preview,e)}function R(){var e,t;this.elements&&(e=this.elements.boundary.getBoundingClientRect(),t=this.elements.preview.getBoundingClientRect(),m(this.elements.overlay,{width:t.width+"px",height:t.height+"px",top:t.top-e.top+"px",left:t.left-e.left+"px"}))}x.prototype.toString=function(){return this.x+"px "+this.y+"px"};B=R,U=500;var B,U,$,Z,Q=function(){var e=this,t=arguments,n=$&&!Z;clearTimeout(Z),Z=setTimeout(function(){Z=null,$||B.apply(e,t)},U),n&&B.apply(e,t)};function I(){var e,t=this,n=t.get();G.call(t)&&(t.options.update.call(t,n),t.$&&"undefined"==typeof Prototype?t.$(t.element).trigger("update.croppie",n):(window.CustomEvent?e=new CustomEvent("update",{detail:n}):(e=document.createEvent("CustomEvent")).initCustomEvent("update",!0,!0,n),t.element.dispatchEvent(e)))}function G(){return 0<this.elements.preview.offsetHeight&&0<this.elements.preview.offsetWidth}function M(){var e=this,t={},n=e.elements.preview,i=new b(0,0,1),o=new x;G.call(e)&&!e.data.bound&&(e.data.bound=!0,t[v]=i.toString(),t[w]=o.toString(),t.opacity=1,m(n,t),o=e.elements.preview.getBoundingClientRect(),e._originalImageWidth=o.width,e._originalImageHeight=o.height,e.data.orientation=E.call(e)?C(e.elements.img):e.data.orientation,e.options.enableZoom?z.call(e,!0):e._currentZoom=1,i.scale=e._currentZoom,t[v]=i.toString(),m(n,t),e.data.points.length?function(e){if(4!==e.length)throw"Croppie - Invalid number of points supplied: "+e;var t=this,n=e[2]-e[0],i=t.elements.viewport.getBoundingClientRect(),o=t.elements.boundary.getBoundingClientRect(),r=i.left-o.left,o=i.top-o.top,i=i.width/n,n=e[1],a=e[0],o=-1*e[1]+o,e=-1*e[0]+r,r={};r[w]=a+"px "+n+"px",r[v]=new b(e,o,i).toString(),m(t.elements.preview,r),_.call(t,i),t._currentZoom=i}.call(e,e.data.points):function(){var e=this,t=e.elements.preview.getBoundingClientRect(),n=e.elements.viewport.getBoundingClientRect(),i=e.elements.boundary.getBoundingClientRect(),o=n.left-i.left,i=n.top-i.top,o=o-(t.width-n.width)/2,i=i-(t.height-n.height)/2,t=new b(o,i,e._currentZoom);m(e.elements.preview,v,t.toString())}.call(e),L.call(e),R.call(e))}function z(e){var t,n=this,i=Math.max(n.options.minZoom,0)||0,o=n.options.maxZoom||1.5,r=n.elements.zoomer,a=parseFloat(r.value),s=n.elements.boundary.getBoundingClientRect(),l=T(n.elements.img,n.data.orientation),u=n.elements.viewport.getBoundingClientRect();n.options.enforceBoundary&&(t=u.width/l.width,u=u.height/l.height,i=Math.max(t,u)),o<=i&&(o=i+1),r.min=W(i,4),r.max=W(o,4),!e&&(a<r.min||a>r.max)?_.call(n,a<r.min?r.min:r.max):e&&(t=Math.max(s.width/l.width,s.height/l.height),u=null!==n.data.boundZoom?n.data.boundZoom:t,_.call(n,u)),d(r)}function F(e){var t=e.points,n=g(t[0]),i=g(t[1]),o=g(t[2])-n,t=g(t[3])-i,r=e.circle,a=document.createElement("canvas"),s=a.getContext("2d"),l=e.outputWidth||o,u=e.outputHeight||t,e=(a.width=l,a.height=u,e.backgroundColor&&(s.fillStyle=e.backgroundColor,s.fillRect(0,0,l,u)),n),c=i,h=o,p=t,d=0,m=0,f=l,v=u;return n<0&&(e=0,d=Math.abs(n)/o*l),h+e>this._originalImageWidth&&(f=(h=this._originalImageWidth-e)/o*l),i<0&&(c=0,m=Math.abs(i)/t*u),p+c>this._originalImageHeight&&(v=(p=this._originalImageHeight-c)/t*u),s.drawImage(this.elements.preview,e,c,h,p,d,m,f,v),r&&(s.fillStyle="#fff",s.globalCompositeOperation="destination-in",s.beginPath(),s.arc(a.width/2,a.height/2,a.width/2,0,2*Math.PI,!0),s.closePath(),s.fill()),a}function V(o,r){var e,a=this,s=[],t=null,n=E.call(a);if("string"==typeof o)e=o,o={};else if(Array.isArray(o))s=o.slice();else{if(void 0===o&&a.data.url)return M.call(a),I.call(a),null;e=o.url,s=o.points||[],t=void 0===o.zoom?null:o.zoom}return a.data.bound=!1,a.data.url=e||a.data.url,a.data.boundZoom=t,function(i,o){var r;if(i)return(r=new Image).style.opacity="0",new Promise(function(e,t){function n(){r.style.opacity="1",setTimeout(function(){e(r)},1)}r.removeAttribute("crossOrigin"),i.match(/^https?:\/\/|^\/\//)&&r.setAttribute("crossOrigin","anonymous"),r.onload=function(){o?EXIF.getData(r,function(){n()}):n()},r.onerror=function(e){r.style.opacity=1,setTimeout(function(){t(e)},1)},r.src=i});throw"Source image missing"}(e,n).then(function(e){var t,n,i;!function(t){this.elements.img.parentNode&&(Array.prototype.forEach.call(this.elements.img.classList,function(e){t.classList.add(e)}),this.elements.img.parentNode.replaceChild(t,this.elements.img),this.elements.preview=t),this.elements.img=t}.call(a,e),s.length?a.options.relative&&(s=[s[0]*e.naturalWidth/100,s[1]*e.naturalHeight/100,s[2]*e.naturalWidth/100,s[3]*e.naturalHeight/100]):(e=T(e),(i=(i=a.elements.viewport.getBoundingClientRect()).width/i.height)<e.width/e.height?t=(n=e.height)*i:(t=e.width,n=e.height/i),i=(e.width-t)/2,e=(e.height-n)/2,a.data.points=[i,e,i+t,e+n]),a.data.orientation=o.orientation||1,a.data.points=s.map(function(e){return parseFloat(e)}),a.options.useCanvas&&function(e){var t=this.elements.canvas,n=this.elements.img,e=(t.getContext("2d").clearRect(0,0,t.width,t.height),t.width=n.width,t.height=n.height,this.options.enableOrientation&&e||C(n));q(t,n,e)}.call(a,a.data.orientation),M.call(a),I.call(a),r&&r()})}function W(e,t){return parseFloat(e).toFixed(t||0)}function J(){var e=this,t=e.elements.preview.getBoundingClientRect(),n=e.elements.viewport.getBoundingClientRect(),i=n.left-t.left,t=n.top-t.top,o=(n.width-e.elements.viewport.offsetWidth)/2,n=(n.height-e.elements.viewport.offsetHeight)/2,o=i+e.elements.viewport.offsetWidth+o,n=t+e.elements.viewport.offsetHeight+n,r=e._currentZoom,a=(r!==1/0&&!isNaN(r)||(r=1),e.options.enforceBoundary?0:Number.NEGATIVE_INFINITY),i=Math.max(a,i/r),t=Math.max(a,t/r),o=Math.max(a,o/r),n=Math.max(a,n/r);return{points:[W(i),W(t),W(o),W(n)],zoom:r,orientation:e.data.orientation}}var X,ee={type:"canvas",format:"png",quality:1},te=["jpeg","webp","png"];function ne(e){var t=this,n=J.call(t),i=h(p(ee),p(e)),o="string"==typeof e?e:i.type||"base64",e=i.size||"viewport",r=i.format,a=i.quality,s=i.backgroundColor,i="boolean"==typeof i.circle?i.circle:"circle"===t.options.viewport.type,l=t.elements.viewport.getBoundingClientRect(),u=l.width/l.height;return"viewport"===e?(n.outputWidth=l.width,n.outputHeight=l.height):"object"==typeof e&&(e.width&&e.height?(n.outputWidth=e.width,n.outputHeight=e.height):e.width?(n.outputWidth=e.width,n.outputHeight=e.width/u):e.height&&(n.outputWidth=e.height*u,n.outputHeight=e.height)),-1<te.indexOf(r)&&(n.format="image/"+r,n.quality=a),n.circle=i,n.url=t.data.url,n.backgroundColor=s,new Promise(function(e){switch(o.toLowerCase()){case"rawcanvas":e(F.call(t,n));break;case"canvas":case"base64":e(function(e){return F.call(this,e).toDataURL(e.format,e.quality)}.call(t,n));break;case"blob":!function(e){var n=this;return new Promise(function(t){F.call(n,e).toBlob(function(e){t(e)},e.format,e.quality)})}.call(t,n).then(e);break;default:e(function(e){var t=e.points,n=document.createElement("div"),i=document.createElement("img"),o=t[2]-t[0],r=t[3]-t[1];return f(n,"croppie-result"),n.appendChild(i),m(i,{left:-1*t[0]+"px",top:-1*t[1]+"px"}),i.src=e.url,m(n,{width:o+"px",height:r+"px"}),n}.call(t,n))}})}function ie(e){if(!this.options.useCanvas||!this.options.enableOrientation)throw"Croppie: Cannot rotate without enableOrientation && EXIF.js included";var t,n,i,o=this,r=o.elements.canvas;o.data.orientation=(n=o.data.orientation,i=e,t=-1<j.indexOf(n)?j:N,n=t.indexOf(n),i=i/90%t.length,t[(t.length+n+i%t.length)%t.length]),q(r,o.elements.img,o.data.orientation),L.call(o,!0),z.call(o),Math.abs(e)/90%2==1&&(n=o._originalImageHeight,i=o._originalImageWidth,o._originalImageWidth=n,o._originalImageHeight=i)}function Y(e,t){if(-1<e.className.indexOf("croppie-container"))throw new Error("Croppie: Can't initialize croppie more than once");this.element=e,this.options=h(p(Y.defaults),t),"img"===this.element.tagName.toLowerCase()&&(f(e=this.element,"cr-original-image"),P(e,{"aria-hidden":"true",alt:""}),t=document.createElement("div"),this.element.parentNode.appendChild(t),t.appendChild(e),this.element=t,this.options.url=this.options.url||e.src),K.call(this),this.options.url&&(t={url:this.options.url,points:this.options.points},delete this.options.url,delete this.options.points,V.call(this,t))}return"undefined"!=typeof window&&window.jQuery&&((X=window.jQuery).fn.croppie=function(n){var i,e;return"string"==typeof n?(i=Array.prototype.slice.call(arguments,1),e=X(this).data("croppie"),"get"===n?e.get():"result"===n?e.result.apply(e,i):"bind"===n?e.bind.apply(e,i):this.each(function(){var e=X(this).data("croppie");if(e){var t=e[n];if(!X.isFunction(t))throw"Croppie "+n+" method not found";t.apply(e,i),"destroy"===n&&X(this).removeData("croppie")}})):this.each(function(){var e=new Y(this,n);(e.$=X)(this).data("croppie",e)})}),Y.defaults={viewport:{width:100,height:100,type:"square"},boundary:{},orientationControls:{enabled:!0,leftClass:"",rightClass:""},resizeControls:{width:!0,height:!0},customClass:"",showZoomer:!0,enableZoom:!0,enableResize:!1,mouseWheelZoom:!0,enableExif:!1,enforceBoundary:!0,enableOrientation:!1,enableKeyMovement:!0,update:function(){}},Y.globals={translate:"translate3d"},h(Y.prototype,{bind:function(e,t){return V.call(this,e,t)},get:function(){var e=J.call(this),t=e.points;return this.options.relative&&(t[0]/=this.elements.img.naturalWidth/100,t[1]/=this.elements.img.naturalHeight/100,t[2]/=this.elements.img.naturalWidth/100,t[3]/=this.elements.img.naturalHeight/100),e},result:function(e){return ne.call(this,e)},refresh:function(){return function(){M.call(this)}.call(this)},setZoom:function(e){_.call(this,e),d(this.elements.zoomer)},rotate:function(e){ie.call(this,e)},destroy:function(){return function(){var e,t,n=this;n.element.removeChild(n.elements.boundary),e=n.element,t="croppie-container",e.classList?e.classList.remove(t):e.className=e.className.replace(t,""),n.options.enableZoom&&n.element.removeChild(n.elements.zoomerWrap),delete n.elements}.call(this)}}),Y});