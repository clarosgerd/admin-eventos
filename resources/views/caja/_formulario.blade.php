{{--
    Formulario compartido de la Caja (alta nueva / edición) — ver
    ApiRestEvent/brain/api_rest_event/PLAN-CAJA-COBRO-PRESENCIAL-14082026.md,
    "Actualización 2": mismo detalle de campos que el formulario público
    (categoría, identidad, contacto, equipo, delivery, souvenirs con
    talla/sexo, donación, promo), no el subset reducido tipo carga CSV.

    Variables esperadas: $evento (array), $modo ('nueva'|'editar'),
    $formTypeFijo (int|null), $prefill (array|null), $costoEdicion
    (float|null), $actionUrl (string), $pagoStatus ('pending'|'paid'|null).
--}}
@php
    $prefill ??= [];
    $formTypes = $evento['formTypes'] ?? [];
@endphp

<form id="cajaForm" method="POST" action="{{ $actionUrl }}" class="space-y-6">
    @csrf
    @if ($modo === 'editar')
        <input type="hidden" name="pago_status" value="{{ $pagoStatus }}">
    @endif
    <input type="hidden" name="participante_json" id="participante_json">
    <input type="hidden" name="totales_json" id="totales_json">

    @if ($modo === 'editar' && $pagoStatus === 'paid')
        <div class="bg-amber-50 border border-amber-300 text-amber-900 rounded-md p-4 text-sm">
            <p class="font-semibold mb-1">Esta inscripción ya está pagada.</p>
            <p>
                Guardar los cambios cobra un adicional configurado de
                <strong>{{ number_format($costoEdicion ?? 0, 2) }}</strong>.
            </p>
            <label class="inline-flex items-center gap-2 mt-2">
                <input type="checkbox" id="confirmarAdicional">
                Confirmo que voy a cobrar ese adicional
            </label>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="font-semibold mb-3">Tipo de inscripción</h2>
        @if ($modo === 'nueva')
            <select id="formTypesId" name="form_types_id" required
                    class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                <option value="">— seleccionar —</option>
                @foreach ($formTypes as $ft)
                    <option value="{{ $ft['id'] }}">{{ $ft['name'] }}</option>
                @endforeach
            </select>
        @else
            @php($ftActual = collect($formTypes)->firstWhere('id', $formTypeFijo))
            <p class="text-sm">{{ $ftActual['name'] ?? ('#'.$formTypeFijo) }}</p>
            <input type="hidden" id="formTypesId" value="{{ $formTypeFijo }}">
        @endif
    </div>

    <div id="categorySection" class="bg-white rounded-lg shadow p-5" style="display:none">
        <h2 class="font-semibold mb-3">Categoría</h2>
        <select id="categoria" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></select>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="font-semibold mb-3">Datos del participante</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label class="block text-xs font-semibold mb-1">Nombre *</label>
                <input type="text" id="f_nombre" value="{{ $prefill['nombre'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Apellido *</label>
                <input type="text" id="f_apellido" value="{{ $prefill['apellido'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Alias</label>
                <input type="text" id="f_alias" value="{{ $prefill['alias'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Género</label>
                <select id="f_genero" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="Masculino" @selected(($prefill['genero'] ?? '') === 'Masculino')>Masculino</option>
                    <option value="Femenino" @selected(($prefill['genero'] ?? '') === 'Femenino')>Femenino</option>
                </select></div>
            <div><label class="block text-xs font-semibold mb-1">Tipo documento</label>
                <select id="f_tipoDocumento" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                    <option value="DNI" @selected(($prefill['tipoDocumento'] ?? 'DNI') === 'DNI')>DNI</option>
                    <option value="Pasaporte" @selected(($prefill['tipoDocumento'] ?? '') === 'Pasaporte')>Pasaporte</option>
                    <option value="Carnet" @selected(($prefill['tipoDocumento'] ?? '') === 'Carnet')>Carnet</option>
                </select></div>
            <div><label class="block text-xs font-semibold mb-1">N° documento *</label>
                <input type="text" id="f_numeroDocumento" value="{{ $prefill['numeroDocumento'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Día nac. *</label>
                <input type="number" min="1" max="31" id="f_nacDia" value="{{ $prefill['nacimientoDia'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Mes nac. *</label>
                <input type="number" min="1" max="12" id="f_nacMes" value="{{ $prefill['nacimientoMes'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Año nac. *</label>
                <input type="number" min="1900" max="{{ date('Y') }}" id="f_nacAnio" value="{{ $prefill['nacimientoAnio'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Correo *</label>
                <input type="email" id="f_correo" value="{{ $prefill['correo'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Teléfono</label>
                <input type="text" id="f_telefono" value="{{ $prefill['telefono'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Dirección</label>
                <input type="text" id="f_direccion" value="{{ $prefill['direccion'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Ciudad</label>
                <input type="text" id="f_ciudad" value="{{ $prefill['ciudad'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="font-semibold mb-3">Contacto de emergencia</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div><label class="block text-xs font-semibold mb-1">Nombre *</label>
                <input type="text" id="f_ceNombre" value="{{ $prefill['contactoNombre'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Celular *</label>
                <input type="text" id="f_ceCelular" value="{{ $prefill['contactoCelular'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Relación *</label>
                <input type="text" id="f_ceRelacion" value="{{ $prefill['contactoRelacion'] ?? '' }}" required class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
        </div>
    </div>

    <div id="equipoSection" class="bg-white rounded-lg shadow p-5" style="display:none">
        <h2 class="font-semibold mb-3">Equipo</h2>
        <select id="f_equipoId" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
            <option value="">— seleccionar —</option>
            @foreach ($evento['equipos'] ?? [] as $eq)
                <option value="{{ $eq['id'] }}" @selected(($prefill['equipoId'] ?? null) == $eq['id'])>{{ $eq['nombre'] }}</option>
            @endforeach
        </select>
    </div>

    <div id="deliverySection" class="bg-white rounded-lg shadow p-5" style="display:none">
        <h2 class="font-semibold mb-3">Delivery del kit</h2>
        <label class="inline-flex items-center gap-2 text-sm mb-3">
            <input type="checkbox" id="f_quiereDelivery" @checked($prefill['quiereDelivery'] ?? false)>
            Quiere delivery
        </label>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-semibold mb-1">Latitud (opcional)</label>
                <input type="number" step="0.000001" id="f_deliveryLat" value="{{ $prefill['deliveryLat'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
            <div><label class="block text-xs font-semibold mb-1">Longitud (opcional)</label>
                <input type="number" step="0.000001" id="f_deliveryLng" value="{{ $prefill['deliveryLng'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm"></div>
        </div>
    </div>

    <div id="souvenirsSection" class="bg-white rounded-lg shadow p-5" style="display:none">
        <h2 class="font-semibold mb-3">Kit / souvenirs</h2>
        <div id="souvenirsList" class="space-y-3"></div>
    </div>

    <div id="donationSection" class="bg-white rounded-lg shadow p-5" style="display:none">
        <h2 class="font-semibold mb-3">Donación</h2>
        <input type="number" step="0.01" min="0" id="f_donacion" value="{{ $prefill['donacion'] ?? 0 }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
    </div>

    <div id="promoSection" class="bg-white rounded-lg shadow p-5" style="display:none">
        <h2 class="font-semibold mb-3">Código promocional</h2>
        <input type="text" id="f_promoCodigo" value="{{ $prefill['promoCodigo'] ?? '' }}" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
        <p id="promoMsg" class="text-xs mt-1"></p>
    </div>

    <div class="bg-white rounded-lg shadow p-5">
        <h2 class="font-semibold mb-3">Resumen de cobro</h2>
        <dl class="text-sm space-y-1">
            <div class="flex justify-between"><dt>Inscripción</dt><dd id="r_inscripcion">0.00</dd></div>
            <div class="flex justify-between"><dt>Souvenirs</dt><dd id="r_souvenirs">0.00</dd></div>
            <div class="flex justify-between"><dt>Descuento promo</dt><dd id="r_descuento">-0.00</dd></div>
            <div class="flex justify-between"><dt>Donación</dt><dd id="r_donacion">0.00</dd></div>
            <div class="flex justify-between"><dt>Cargo de servicio</dt><dd id="r_fee">0.00</dd></div>
            <div class="flex justify-between font-bold text-base border-t pt-1 mt-1"><dt>Total a cobrar</dt><dd id="r_total">0.00</dd></div>
        </dl>
    </div>

    <button type="submit" id="btnSubmit"
            class="w-full bg-brand-600 hover:bg-brand-700 text-white rounded-md px-3 py-3 text-sm font-semibold">
        {{ $modo === 'nueva' ? 'Cobrar y confirmar' : ($pagoStatus === 'paid' ? 'Confirmar y cobrar adicional' : 'Guardar cambios') }}
    </button>
</form>

<script>
(function () {
    const EVENTO = {!! json_encode($evento) !!};
    const PREFILL_SOUVENIRS = {!! json_encode($prefill['souvenirs'] ?? []) !!};
    const FEE_PCT = Number(EVENTO.fee_pct || 0);
    const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
    const SEXOS = ['Masculino', 'Femenino'];

    function formTypeActual() {
        const id = document.getElementById('formTypesId').value;
        return (EVENTO.formTypes || []).find(ft => String(ft.id) === String(id));
    }

    function renderCategoria(ft) {
        const section = document.getElementById('categorySection');
        const select = document.getElementById('categoria');
        if (!ft || !ft.requiereCategoria) { section.style.display = 'none'; select.innerHTML = ''; return; }
        section.style.display = '';
        select.innerHTML = '<option value="">— seleccionar —</option>' + (EVENTO.categories || []).map(c =>
            `<option value="${c.id}" data-precio="${c.precio_vigente}">${c.name} (${Number(c.precio_vigente).toFixed(2)})</option>`
        ).join('');
        const pre = {{ json_encode($prefill['categoria'] ?? null) }};
        if (pre) select.value = String(pre);
    }

    function renderEquipoDelivery(ft) {
        document.getElementById('equipoSection').style.display = (ft && ft.hasTeam) ? '' : 'none';
        document.getElementById('deliverySection').style.display = (ft && ft.hasDelivery) ? '' : 'none';
        document.getElementById('donationSection').style.display = (ft && ft.hasDonation) ? '' : 'none';
        document.getElementById('promoSection').style.display = (ft && ft.hasPromoCode) ? '' : 'none';
    }

    function souvenirPrefillFor(id) {
        return PREFILL_SOUVENIRS.find(s => String(s.id) === String(id)) || null;
    }

    function renderSouvenirs(ft) {
        const section = document.getElementById('souvenirsSection');
        const list = document.getElementById('souvenirsList');
        const souvenirs = (ft && ft.souvenirs) || [];
        if (souvenirs.length === 0) { section.style.display = 'none'; list.innerHTML = ''; return; }
        section.style.display = '';
        list.innerHTML = souvenirs.map(sv => {
            const pre = souvenirPrefillFor(sv.id);
            const checked = pre ? 'checked' : '';
            let variantHtml = '';
            if (sv.requiere_talla || sv.requiere_sexo) {
                const tallaOpts = TALLAS.map(t => `<option value="${t}" ${pre && pre.talla === t ? 'selected' : ''}>${t}</option>`).join('');
                const sexoOpts = SEXOS.map(s => `<option value="${s}" ${pre && pre.sexo === s ? 'selected' : ''}>${s}</option>`).join('');
                variantHtml = `<span class="ml-3 inline-flex gap-2">` +
                    (sv.requiere_talla ? `<select class="sv-talla border border-slate-300 rounded text-xs px-1 py-0.5" data-sv="${sv.id}"><option value="">Talla</option>${tallaOpts}</select>` : '') +
                    (sv.requiere_sexo ? `<select class="sv-sexo border border-slate-300 rounded text-xs px-1 py-0.5" data-sv="${sv.id}"><option value="">Sexo</option>${sexoOpts}</select>` : '') +
                    `</span>`;
            }
            return `<label class="flex items-center text-sm">
                <input type="checkbox" class="sv-check mr-2" data-sv="${sv.id}" data-precio="${sv.price}" data-incluido="${sv.incluido ? 1 : 0}" data-nombre="${sv.name}" ${checked}>
                ${sv.name} — ${sv.incluido ? 'incluido' : Number(sv.price).toFixed(2)}
                ${variantHtml}
            </label>`;
        }).join('');
    }

    function souvenirsSeleccionados() {
        return Array.from(document.querySelectorAll('.sv-check:checked')).map(chk => {
            const id = chk.dataset.sv;
            const talla = document.querySelector(`.sv-talla[data-sv="${id}"]`);
            const sexo = document.querySelector(`.sv-sexo[data-sv="${id}"]`);
            return {
                id: Number(id),
                nombre: chk.dataset.nombre,
                precio: chk.dataset.incluido === '1' ? 0 : Number(chk.dataset.precio),
                talla: talla ? (talla.value || null) : null,
                sexo: sexo ? (sexo.value || null) : null,
            };
        });
    }

    function promoAplicable() {
        const codigo = (document.getElementById('f_promoCodigo')?.value || '').trim();
        const msg = document.getElementById('promoMsg');
        if (!codigo) { if (msg) msg.textContent = ''; return null; }
        const promo = (EVENTO.promoCodes || []).find(p => p.promo_code === codigo);
        if (!promo) { if (msg) { msg.textContent = 'Código no encontrado.'; msg.className = 'text-xs mt-1 text-red-600'; } return null; }
        if (promo.usado) { if (msg) { msg.textContent = 'Este código ya fue usado.'; msg.className = 'text-xs mt-1 text-red-600'; } return null; }
        if (msg) { msg.textContent = 'Código válido.'; msg.className = 'text-xs mt-1 text-green-600'; }
        return promo;
    }

    function calcular() {
        const ft = formTypeActual();
        let inscripcion = 0;
        if (ft) {
            if (ft.requiereCategoria) {
                const opt = document.getElementById('categoria')?.selectedOptions?.[0];
                inscripcion = opt ? Number(opt.dataset.precio || 0) : 0;
            } else {
                inscripcion = Number(ft.precio_base || 0);
            }
        }

        const souvenirsTotal = souvenirsSeleccionados().reduce((sum, s) => sum + s.precio, 0);
        const donacion = ft && ft.hasDonation ? Number(document.getElementById('f_donacion')?.value || 0) : 0;

        let descuento = 0;
        const promo = ft && ft.hasPromoCode ? promoAplicable() : null;
        if (promo) {
            descuento = promo.discount_type === 'percentage'
                ? Math.round(inscripcion * Number(promo.discount_percent || 0) * 100) / 100
                : Math.max(0, Math.round((inscripcion - Number(promo.price || 0)) * 100) / 100);
        }

        const baseConDescuento = Math.max(0, inscripcion - descuento);
        const fee = Math.round(baseConDescuento * FEE_PCT * 100) / 100;
        const total = Math.round((baseConDescuento + souvenirsTotal + donacion + fee) * 100) / 100;

        document.getElementById('r_inscripcion').textContent = inscripcion.toFixed(2);
        document.getElementById('r_souvenirs').textContent = souvenirsTotal.toFixed(2);
        document.getElementById('r_descuento').textContent = '-' + descuento.toFixed(2);
        document.getElementById('r_donacion').textContent = donacion.toFixed(2);
        document.getElementById('r_fee').textContent = fee.toFixed(2);
        document.getElementById('r_total').textContent = total.toFixed(2);

        return {
            inscripcion, donacion, souvenirs: souvenirsTotal, fee,
            descuento, descuento_registrante: 0, grand_total: total,
        };
    }

    function actualizarSecciones() {
        const ft = formTypeActual();
        renderCategoria(ft);
        renderEquipoDelivery(ft);
        renderSouvenirs(ft);
        calcular();
    }

    document.getElementById('formTypesId').addEventListener('change', actualizarSecciones);
    document.addEventListener('change', (e) => {
        if (e.target.closest('#cajaForm')) calcular();
    });
    document.addEventListener('input', (e) => {
        if (e.target.closest('#cajaForm')) calcular();
    });

    document.getElementById('cajaForm').addEventListener('submit', function (e) {
        const gate = document.getElementById('confirmarAdicional');
        if (gate && !gate.checked) {
            e.preventDefault();
            alert('Tenés que confirmar el cobro del adicional antes de guardar.');
            return;
        }

        const totales = calcular();
        const categoriaSelect = document.getElementById('categoria');
        const participante = {
            nombre: document.getElementById('f_nombre').value,
            apellido: document.getElementById('f_apellido').value,
            alias: document.getElementById('f_alias').value,
            genero: document.getElementById('f_genero').value,
            tipoDocumento: document.getElementById('f_tipoDocumento').value,
            numeroDocumento: document.getElementById('f_numeroDocumento').value,
            polera: '', precioPolera: 0,
            nacimiento: {
                dia: Number(document.getElementById('f_nacDia').value),
                mes: Number(document.getElementById('f_nacMes').value),
                anio: Number(document.getElementById('f_nacAnio').value),
            },
            edad: Math.max(0, new Date().getFullYear() - Number(document.getElementById('f_nacAnio').value || 0)),
            correo: document.getElementById('f_correo').value,
            direccion: document.getElementById('f_direccion').value,
            ciudad: document.getElementById('f_ciudad').value,
            telefono: document.getElementById('f_telefono').value,
            contacto_emergencia: {
                nombre: document.getElementById('f_ceNombre').value,
                celular: document.getElementById('f_ceCelular').value,
                relacion: document.getElementById('f_ceRelacion').value,
            },
            souvenirs: souvenirsSeleccionados(),
            answers: [],
            categoria: categoriaSelect && categoriaSelect.value ? categoriaSelect.value : '0',
            precioCategoria: totales.inscripcion,
            donacion: totales.donacion,
            promoDescuento: totales.descuento,
            promoCodigo: document.getElementById('f_promoCodigo')?.value || '',
            subtotal: totales.inscripcion,
            equipoId: document.getElementById('f_equipoId')?.value ? Number(document.getElementById('f_equipoId').value) : null,
            quiereDelivery: !!document.getElementById('f_quiereDelivery')?.checked,
            deliveryLat: document.getElementById('f_deliveryLat')?.value ? Number(document.getElementById('f_deliveryLat').value) : null,
            deliveryLng: document.getElementById('f_deliveryLng')?.value ? Number(document.getElementById('f_deliveryLng').value) : null,
        };

        document.getElementById('participante_json').value = JSON.stringify(participante);
        document.getElementById('totales_json').value = JSON.stringify(totales);
    });

    actualizarSecciones();
})();
</script>
