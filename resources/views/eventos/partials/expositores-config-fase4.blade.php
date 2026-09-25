                    {{-- Fase 4 (26/09/2026) — mapa de especialidades, pasaporte médico y
                         correo de seguimiento. Las preguntas candidatas salen de los tipos de
                         formulario del evento (una misma etiqueta repetida en varios tipos se
                         cuenta una sola vez: la API compara por etiqueta sin acentos/mayúsculas). --}}
                    @php
                        $cfgExp = $evento['expositoresConfig'] ?? [];
                        $clavePregunta = fn (string $t) => \Illuminate\Support\Str::of($t)->lower()->ascii()->squish()->toString();
                        $etiquetasPregunta = [];
                        foreach ($evento['formTypes'] ?? [] as $ftPregunta) {
                            foreach ($ftPregunta['preguntas'] ?? [] as $preguntaOpcion) {
                                $etiqueta = trim((string) ($preguntaOpcion['etiqueta'] ?? ''));
                                if ($etiqueta !== '') {
                                    $etiquetasPregunta[$clavePregunta($etiqueta)] ??= $etiqueta;
                                }
                            }
                        }
                        $especialidadActual = (string) old('expositoresEspecialidadPregunta', $cfgExp['especialidad_pregunta'] ?? '');
                        $institucionActual = (string) old('expositoresInstitucionPregunta', $cfgExp['institucion_pregunta'] ?? '');
                        // Si lo guardado ya no existe entre las preguntas, igual se ofrece para no perderlo en silencio.
                        $opcionesEspecialidad = $etiquetasPregunta;
                        if ($especialidadActual !== '') {
                            $opcionesEspecialidad[$clavePregunta($especialidadActual)] ??= $especialidadActual;
                        }
                        $opcionesInstitucion = $etiquetasPregunta;
                        if ($institucionActual !== '') {
                            $opcionesInstitucion[$clavePregunta($institucionActual)] ??= $institucionActual;
                        }
                        $seguimientoOn = (bool) old('expositoresSeguimientoHabilitado', $cfgExp['seguimiento_habilitado'] ?? false);
                        $seguimientoTyc = (bool) old('expositoresSeguimientoTyc', !empty($cfgExp['seguimiento_tyc_confirmado_at']));
                    @endphp
                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <p class="text-sm font-semibold mb-1">Mapa de especialidades <span class="font-normal text-slate-500">(gráficos del panel de cada expositor)</span></p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1" for="expositoresEspecialidadPregunta">Pregunta que es la <strong>especialidad</strong></label>
                                <select id="expositoresEspecialidadPregunta" name="expositoresEspecialidadPregunta" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">— No mostrar —</option>
                                    @foreach ($opcionesEspecialidad as $claveOpcion => $etiquetaOpcion)
                                        <option value="{{ $etiquetaOpcion }}" @selected($clavePregunta($especialidadActual) === $claveOpcion)>{{ $etiquetaOpcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1" for="expositoresInstitucionPregunta">Pregunta que es la <strong>institución</strong> / hospital</label>
                                <select id="expositoresInstitucionPregunta" name="expositoresInstitucionPregunta" class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                                    <option value="">— No mostrar —</option>
                                    @foreach ($opcionesInstitucion as $claveOpcion => $etiquetaOpcion)
                                        <option value="{{ $etiquetaOpcion }}" @selected($clavePregunta($institucionActual) === $claveOpcion)>{{ $etiquetaOpcion }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Si la pregunta es una lista desplegable el mapa sale limpio; si es texto libre, las variantes de escritura
                            ("Anestesiólogo", "anestesiologo") se agrupan, pero errores de tipeo más grandes quedan separados.
                        </p>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <p class="text-sm font-semibold mb-1">Pasaporte médico <span class="font-normal text-slate-500">(sorteo general entre quienes visitaron varios stands)</span></p>
                        <div class="w-48">
                            <label class="block text-xs text-slate-500 mb-1" for="expositoresPasaporteMinStands">Stands distintos mínimos</label>
                            <input type="number" id="expositoresPasaporteMinStands" name="expositoresPasaporteMinStands" min="1" max="50" placeholder="5"
                                   value="{{ old('expositoresPasaporteMinStands', $cfgExp['pasaporte_min_stands'] ?? '') }}"
                                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        </div>
                        @if (!empty($evento['id']))
                            <p class="text-xs text-slate-500 mt-1">
                                Vacío = 5. El sorteo se hace desde
                                <a href="{{ route('pasaporte.show', $evento['id']) }}" class="text-brand-600 hover:underline">Pasaporte médico →</a>
                            </p>
                        @endif
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <p class="text-sm font-semibold mb-1">Correo de seguimiento de los expositores</p>
                        <p class="text-xs text-slate-500 mb-2">
                            Apagado por defecto. Encendido, cada expositor puede enviar un correo de agradecimiento al asistente que
                            captura (máximo uno por asistente por empresa, con enlace para darse de baja). Los correos salen de la
                            plataforma con el nombre de la empresa, y el mensaje no puede llevar links (solo un enlace de catálogo).
                        </p>
                        <label class="flex items-center gap-2 text-sm mb-1">
                            <input type="checkbox" name="expositoresSeguimientoHabilitado" value="1" id="expositoresSeguimientoHabilitado" @checked($seguimientoOn)>
                            Habilitar el correo de seguimiento en este evento
                        </label>
                        <label class="flex items-start gap-2 text-sm mb-2 ml-6">
                            <input type="checkbox" name="expositoresSeguimientoTyc" value="1" id="expositoresSeguimientoTyc" class="mt-0.5" @checked($seguimientoTyc)>
                            <span>Confirmo que los <strong>Términos y Condiciones</strong> del evento informan a los asistentes que los expositores pueden contactarlos.
                                <span class="text-slate-500">(Obligatorio para habilitar.)</span></span>
                        </label>
                        <input type="hidden" name="expositoresSeguimientoTycAt" value="{{ $cfgExp['seguimiento_tyc_confirmado_at'] ?? '' }}">
                        <div class="w-64 ml-6">
                            <label class="block text-xs text-slate-500 mb-1" for="expositoresSeguimientoMax">Máx. correos por asistente en todo el evento</label>
                            <input type="number" id="expositoresSeguimientoMax" name="expositoresSeguimientoMax" min="1" max="100" placeholder="10"
                                   value="{{ old('expositoresSeguimientoMax', $cfgExp['seguimiento_max_por_asistente'] ?? '') }}"
                                   class="w-full border border-slate-300 rounded-md px-3 py-2 text-sm">
                        </div>
                    </div>
