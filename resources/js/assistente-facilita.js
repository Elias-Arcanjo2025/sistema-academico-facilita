export default function assistenteFacilita(config = {}) {
    const routes = config.routes || {};
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const storageKey = config.storageKey || 'facilita_assistente_conversa';

    const jsonHeaders = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
    };

    return {
        aberto: false,
        fullscreenMobile: false,
        mostrarHistorico: false,
        texto: '',
        mensagens: [],
        conversas: [],
        conversaId: null,
        aEnviar: false,
        ultimaPergunta: null,
        sugestoes: config.sugestoes || [],
        acoes: config.acoes || [],
        routeName: config.routeName || null,

        toggle() {
            this.aberto = !this.aberto;
            if (!this.aberto) {
                this.mostrarHistorico = false;
            } else {
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },

        abrir() {
            this.aberto = true;
            this.$nextTick(() => {
                this.$refs.input?.focus();
                this.scrollBottom();
            });
        },

        async init() {
            window.addEventListener('assistente:open', () => this.abrir());
            window.addEventListener('assistente:close', () => {
                this.aberto = false;
                this.mostrarHistorico = false;
            });
            this.mostrarHistorico = false;

            const guardada = localStorage.getItem(storageKey);
            if (guardada) {
                const id = parseInt(guardada, 10);
                if (id) {
                    await this.abrirConversa(id, false);
                }
            }

            await this.carregarConversas();
        },

        persistirConversa() {
            if (this.conversaId) {
                localStorage.setItem(storageKey, String(this.conversaId));
            } else {
                localStorage.removeItem(storageKey);
            }
        },

        historicoUrl(id) {
            return (routes.historico || '').replace('__CID__', String(id));
        },

        feedbackUrl(id) {
            return (routes.feedback || '').replace('__MID__', String(id));
        },

        usarSugestao(texto) {
            this.texto = texto;
            this.enviar();
        },

        async toggleHistorico() {
            this.mostrarHistorico = !this.mostrarHistorico;
            if (this.mostrarHistorico) {
                await this.carregarConversas();
            }
        },

        async carregarConversas() {
            if (!routes.conversas) {
                return;
            }

            try {
                const res = await fetch(routes.conversas, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (data.success) {
                    this.conversas = data.conversas || [];
                }
            } catch (e) {
                console.error(e);
            }
        },

        async abrirConversa(id, abrirPainel = true) {
            if (!id || !routes.historico) {
                return;
            }

            try {
                const res = await fetch(this.historicoUrl(id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!data.success) {
                    return;
                }

                this.conversaId = data.conversa_id || id;
                this.mensagens = (data.mensagens || []).map((m) => ({
                    id: m.id,
                    papel: m.papel,
                    conteudo: m.conteudo,
                    feedback: m.feedback || null,
                    retryable: false,
                    acoes: [],
                }));
                this.mostrarHistorico = false;
                this.persistirConversa();
                if (abrirPainel) {
                    this.abrir();
                }
                this.scrollBottom();
            } catch (e) {
                console.error(e);
            }
        },

        async novaConversa() {
            if (!routes.novaConversa) {
                return;
            }

            try {
                const res = await fetch(routes.novaConversa, {
                    method: 'POST',
                    headers: jsonHeaders,
                });
                const data = await res.json();
                if (data.success) {
                    this.conversaId = data.conversa_id;
                    this.mensagens = [];
                    this.ultimaPergunta = null;
                    this.mostrarHistorico = false;
                    this.persistirConversa();
                    await this.carregarConversas();
                }
            } catch (e) {
                console.error(e);
            }
        },

        async enviar() {
            const msg = this.texto.trim();
            if (!msg || this.aEnviar || !routes.mensagem) {
                return;
            }

            this.ultimaPergunta = msg;
            this.aberto = true;
            this.mensagens.push({ papel: 'user', conteudo: msg });
            this.texto = '';
            this.aEnviar = true;
            this.scrollBottom();

            try {
                const res = await fetch(routes.mensagem, {
                    method: 'POST',
                    headers: jsonHeaders,
                    body: JSON.stringify({
                        mensagem: msg,
                        conversa_id: this.conversaId,
                        route: this.routeName,
                    }),
                    redirect: 'manual',
                });

                if (res.type === 'opaqueredirect' || res.status === 0 || (res.status >= 300 && res.status < 400)) {
                    this.pushErro('Sessão ou permissão. Recarregue a página e tente novamente.', false);
                    return;
                }

                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    this.pushErro('Erro de resposta do servidor. Recarregue a página e tente novamente.', true);
                    return;
                }

                const data = await res.json();

                if (res.status === 429) {
                    this.pushErro('Limite atingido. ' + (data.message || 'Aguarde um momento antes de enviar mais mensagens.'), false);
                    return;
                }

                if (!data.success) {
                    this.pushErro(data.message || 'Não foi possível obter resposta.', data.retryable !== false);
                    return;
                }

                this.conversaId = data.conversa_id;
                this.persistirConversa();
                this.mensagens.push({
                    id: data.mensagem_id || null,
                    papel: 'assistant',
                    conteudo: data.resposta,
                    feedback: null,
                    retryable: false,
                    acoes: data.acoes || [],
                });
                await this.carregarConversas();
            } catch (e) {
                this.pushErro('Erro de ligação. Verifique a sua internet e tente novamente.', true);
            } finally {
                this.aEnviar = false;
                this.scrollBottom();
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },

        pushErro(texto, retryable) {
            this.mensagens.push({
                papel: 'assistant',
                conteudo: texto,
                retryable: !!retryable,
                feedback: null,
                acoes: [],
            });
        },

        retryUltima() {
            if (!this.ultimaPergunta || this.aEnviar) {
                return;
            }
            this.texto = this.ultimaPergunta;
            this.enviar();
        },

        async enviarFeedback(msg, valor) {
            if (!msg?.id || !routes.feedback) {
                return;
            }

            try {
                const res = await fetch(this.feedbackUrl(msg.id), {
                    method: 'POST',
                    headers: jsonHeaders,
                    body: JSON.stringify({ feedback: valor }),
                });
                const data = await res.json();
                if (data.success) {
                    msg.feedback = data.feedback;
                }
            } catch (e) {
                console.error(e);
            }
        },

        scrollBottom() {
            this.$nextTick(() => {
                const el = this.$refs.messages;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },

        formatarInline(texto) {
            if (!texto) {
                return '';
            }

            let t = this.escapeHtml(String(texto));
            t = t.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]+)\)/g, (full, label, url) => {
                const safeUrl = url.startsWith('/') || url.startsWith(window.location.origin)
                    ? url
                    : '#';
                if (safeUrl === '#') {
                    return label;
                }
                return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${label}</a>`;
            });
            t = t.replace(/\*\*\s*([\s\S]+?)\s*\*\*/g, '<strong>$1</strong>');
            t = t.replace(/__\s*([\s\S]+?)\s*__/g, '<strong>$1</strong>');
            t = t.replace(/\*\*/g, '');
            t = t.replace(/(^|[\s([{])\*([^*\n]+?)\*([\s)\]}.,;:!?]|)/g, '$1<em>$2</em>$3');

            return t;
        },

        limparMarkdownRotulo(texto) {
            return String(texto || '')
                .replace(/\*\*/g, '')
                .replace(/^__|__$/g, '')
                .trim();
        },

        formatarLinhaDisciplina(linha) {
            const match = linha.match(/^(.+?):\s*(.+)$/);
            if (!match) {
                return null;
            }

            const disciplina = this.limparMarkdownRotulo(match[1]);
            const resto = match[2];

            if (!/\b(MAC|PP|PT|MT|PG|MFT|CF|CA|CFD|REC)\d?\b/i.test(resto)) {
                return null;
            }

            const badges = resto.split(/;\s*/).flatMap((bloco) =>
                bloco.split(/,\s*/).map((parte) => {
                    const m = parte.trim().match(/^([A-Za-z]+\d?)\s+([\d,.]+|—|-)/);
                    if (!m) {
                        const cfd = parte.trim().match(/^CFD\s+([\d,.]+)/i);
                        if (cfd) {
                            const num = parseFloat(cfd[1].replace(',', '.'));
                            const cls = !Number.isNaN(num) && num < 10 ? ' is-low' : '';
                            return `<span class="assistente-badge${cls}"><span class="assistente-badge-label">CFD</span> ${cfd[1]}</span>`;
                        }
                        return `<span class="assistente-badge">${this.formatarInline(parte.trim())}</span>`;
                    }
                    const rotulo = m[1].replace(/^PT3$/i, 'PG');
                    const valor = m[2];
                    const num = parseFloat(valor.replace(',', '.').replace('—', '').replace('-', ''));
                    const cls = !Number.isNaN(num) && num < 10 ? ' is-low' : '';

                    return `<span class="assistente-badge${cls}"><span class="assistente-badge-label">${rotulo}</span> ${valor}</span>`;
                })
            );

            return `<div class="assistente-disciplina"><span class="assistente-disciplina-nome">${this.formatarInline(disciplina)}</span><div class="assistente-badges">${badges.join('')}</div></div>`;
        },

        isTableRow(line) {
            const t = line.trim();
            return t.startsWith('|') && t.endsWith('|') && t.includes('|');
        },

        isTableSeparator(line) {
            return /^\|[\s\-:|]+\|$/.test(line.trim());
        },

        formatarTabela(linhas) {
            const rows = linhas.filter((l) => !this.isTableSeparator(l));
            if (rows.length === 0) {
                return '';
            }

            const parseRow = (row) =>
                row
                    .trim()
                    .replace(/^\|/, '')
                    .replace(/\|$/, '')
                    .split('|')
                    .map((c) => c.trim());

            const header = parseRow(rows[0]);
            const body = rows.slice(1).map(parseRow);

            let html = '<div class="assistente-table-wrap"><table class="assistente-table"><thead><tr>';
            header.forEach((h) => {
                html += `<th>${this.formatarInline(h)}</th>`;
            });
            html += '</tr></thead><tbody>';
            body.forEach((cells) => {
                html += '<tr>';
                cells.forEach((cell) => {
                    html += `<td>${this.formatarInline(cell)}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            return html;
        },

        isSectionHeader(line) {
            const t = line.trim();
            if (/^#{1,3}\s+/.test(t)) {
                return true;
            }
            if (/^\*\*[^*]+\*\*:?\s*$/.test(t)) {
                return true;
            }
            if (/^[A-ZÀ-Ú][^:]{2,50}:\s*$/.test(t) && !t.includes('|')) {
                return true;
            }

            return false;
        },

        formatSectionHeader(line) {
            let t = line.trim().replace(/^#{1,3}\s+/, '');
            t = this.limparMarkdownRotulo(t).replace(/:$/, '');

            return `<h4 class="assistente-section">${this.escapeHtml(t)}</h4>`;
        },

        escapeHtml(texto) {
            return String(texto || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        isKeyValue(line) {
            const t = line.trim();
            if (t.startsWith('-') || t.startsWith('*') || this.isTableRow(t)) {
                return false;
            }
            const m = t.match(/^([^:|]+):\s*(.+)$/);

            return m && m[1].length < 50 && !m[1].includes('|');
        },

        formatKeyValueGrid(items) {
            const html = items
                .map(([label, value]) => {
                    const rotulo = this.limparMarkdownRotulo(label);
                    const valor = String(value || '').replace(/^\*\*\s*/, '').replace(/\s*\*\*$/, '');

                    return `<div class="assistente-kv"><span class="assistente-kv-label">${this.formatarInline(rotulo)}</span><span class="assistente-kv-value">${this.formatarInline(valor)}</span></div>`;
                })
                .join('');

            return `<div class="assistente-kv-grid">${html}</div>`;
        },

        formatar(texto) {
            if (!texto) {
                return '';
            }

            let raw = texto.replace(/\bPT3\b/gi, 'PG');
            raw = raw
                .split('\n')
                .map((line) => line.replace(/^\*\s+/, '- '))
                .join('\n');

            const lines = raw.split('\n');
            const parts = [];
            let listType = null;
            let kvBuffer = [];
            let i = 0;

            const flushKv = () => {
                if (kvBuffer.length > 0) {
                    parts.push(this.formatKeyValueGrid(kvBuffer));
                    kvBuffer = [];
                }
            };

            const closeList = () => {
                if (listType) {
                    parts.push(listType === 'ol' ? '</ol>' : '</ul>');
                    listType = null;
                }
            };

            while (i < lines.length) {
                const trimmed = lines[i].trim();

                if (trimmed === '') {
                    flushKv();
                    closeList();
                    i++;
                    continue;
                }

                if (this.isTableRow(trimmed)) {
                    flushKv();
                    closeList();
                    const tableLines = [];
                    while (i < lines.length && this.isTableRow(lines[i].trim())) {
                        tableLines.push(lines[i].trim());
                        i++;
                    }
                    parts.push(this.formatarTabela(tableLines));
                    continue;
                }

                if (this.isSectionHeader(trimmed)) {
                    flushKv();
                    closeList();
                    parts.push(this.formatSectionHeader(trimmed));
                    i++;
                    continue;
                }

                const bullet = trimmed.match(/^[\*\-•]\s+(.+)$/);
                const numbered = trimmed.match(/^\d+\.\s+(.+)$/);

                if (bullet) {
                    const content = bullet[1];
                    const kvMatch = content.match(/^([^:]+):\s*(.+)$/);
                    if (kvMatch && kvMatch[1].length < 45 && !kvMatch[1].includes('|')) {
                        flushKv();
                        closeList();
                        kvBuffer.push([kvMatch[1].trim(), kvMatch[2].trim()]);
                        i++;
                        continue;
                    }

                    const disciplinaHtml = this.formatarLinhaDisciplina(content);
                    if (disciplinaHtml) {
                        flushKv();
                        closeList();
                        parts.push(disciplinaHtml);
                        i++;
                        continue;
                    }

                    flushKv();
                    if (listType !== 'ul') {
                        closeList();
                        parts.push('<ul class="assistente-list">');
                        listType = 'ul';
                    }
                    parts.push(`<li>${this.formatarInline(content)}</li>`);
                    i++;
                    continue;
                }

                if (numbered) {
                    flushKv();
                    if (listType !== 'ol') {
                        closeList();
                        parts.push('<ol class="assistente-list assistente-list--ordered">');
                        listType = 'ol';
                    }
                    parts.push(`<li>${this.formatarInline(numbered[1])}</li>`);
                    i++;
                    continue;
                }

                if (this.isKeyValue(trimmed)) {
                    closeList();
                    const m = trimmed.match(/^([^:]+):\s*(.+)$/);
                    kvBuffer.push([m[1].trim(), m[2].trim()]);
                    i++;
                    continue;
                }

                flushKv();
                closeList();

                const disciplinaHtml = this.formatarLinhaDisciplina(trimmed);
                if (disciplinaHtml) {
                    parts.push(disciplinaHtml);
                    i++;
                    continue;
                }

                parts.push(`<p class="assistente-p">${this.formatarInline(trimmed)}</p>`);
                i++;
            }

            flushKv();
            closeList();

            return `<div class="assistente-md">${parts.join('')}</div>`;
        },
    };
}
