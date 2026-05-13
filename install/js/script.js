const DEFAULT_DESCRIPTION =
    'Исправления:\n<ul>\n    <li>Описание обновления</li>\n</ul>\n' +
    'Внимание!!! Перед установкой обновления ОБЯЗАТЕЛЬНО выполните полное резервное копирование сайта. ' +
    'Если вы вносили изменения в шаблон решения, то дополнительно скопируйте всю папку шаблона!' +
    '<br><br>\n' +
    'Если у Вас есть пожелания по работе решение, пишете нам на почту support@web-comp.ru ' +
    'и мы всё доработаем.<br><br>';

class ReleaseBuilder {
    constructor() {
        this.initEditors();
        this.bindEvents();
    }

    initEditors() {
        this.quill = new Quill('#quillEditor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
        });
        this.quill.on('text-change', () => {
            document.getElementById('description').value = this.quill.root.innerHTML;
        });
    }

    setDefaultDescription() {
        if (this.quill) {
            this.quill.clipboard.dangerouslyPasteHTML(DEFAULT_DESCRIPTION);
            document.getElementById('description').value = this.quill.root.innerHTML;
        }
    }

    bindEvents() {
        document.querySelectorAll('[data-event]').forEach(el => {
            const [eventType, method] = el.dataset.event.split('.');
            el.addEventListener(eventType, e => this[method]?.(e, el));
        });
    }

    /**
     * Универсальный метод для вызова Bitrix-контроллера.
     */
    async runAction(action, data = {}) {
        try {
            const response = await BX.ajax.runAction(
                'webcomp:releasebuilder.ReleaseBuilder.' + action,
                { data }
            );
            return { success: true, data: response.data };
        } catch (response) {
            const message = response.errors?.[0]?.message ?? 'Ошибка запроса. Проверьте соединение.';
            return { success: false, error: message };
        }
    }

    getFormData() {
        return {
            module:       document.getElementById('module').value,
            version:      document.getElementById('version').value,
            newVersion:   document.getElementById('newVersion').value,
            date:         document.getElementById('date').value,
            description:  document.getElementById('description').value,
            templateName: document.getElementById('templateName').value,
            type:         document.querySelector('[name="type"]:checked')?.value ?? 'regular',
        };
    }

    showError(message) {
        const el = document.getElementById('errorAlert');
        el.textContent = message;
        el.classList.remove('d-none');
    }

    hideError() {
        document.getElementById('errorAlert').classList.add('d-none');
    }

    updateTemplateNameVisibility() {
        const isTemplate = document.querySelector('[name="type"]:checked')?.value === 'template';
        const group      = document.getElementById('templateNameGroup');
        const input      = document.getElementById('templateName');
        const hasModule  = !!document.getElementById('module').value;

        if (isTemplate) {
            group.classList.remove('d-none');
        } else {
            group.classList.add('d-none');
        }
        input.disabled = !isTemplate || !hasModule;
    }

    onTypeChange() {
        this.updateTemplateNameVisibility();
        this.updateSearchButton();
    }

    updateSearchButton() {
        const hasModule   = !!document.getElementById('module').value;
        const isTemplate  = document.querySelector('[name="type"]:checked')?.value === 'template';
        const hasTemplate = !!document.getElementById('templateName').value.trim();

        document.getElementById('btnSearch').disabled = !hasModule || (isTemplate && !hasTemplate);
    }

    async getModule(e, el) {
        const module = el.value;
        if (!module) {
            document.getElementById('btnSearch').disabled         = true;
            document.getElementById('btnArchive').disabled        = true;
            document.getElementById('btnSaveFilters').disabled    = true;
            document.getElementById('filterPatterns').disabled    = true;
            document.getElementById('filterPatterns').value       = '';
            document.getElementById('templateName').value         = '';
            document.getElementById('templateSuggestions').innerHTML = '';
            this.updateTemplateNameVisibility();
            return;
        }

        this.hideError();
        el.disabled = true;

        const [versionResult, configResult] = await Promise.all([
            this.runAction('version', { module }),
            this.runAction('getConfig', { module }),
        ]);

        el.disabled = false;

        if (!versionResult.success) {
            this.showError(versionResult.error);
            return;
        }

        const { VERSION, VERSION_DATE } = versionResult.data;

        document.getElementById('version').value    = VERSION;
        document.getElementById('newVersion').value = this.incrementVersion(VERSION);

        if (VERSION_DATE) {
            const d   = new Date(VERSION_DATE.replace(' ', 'T'));
            const pad = n => String(n).padStart(2, '0');
            const local = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            document.getElementById('date').value = local;
        }

        const filterPatterns = document.getElementById('filterPatterns');
        if (configResult.success) {
            filterPatterns.value = configResult.data.patterns.join('\n');

            const detectedType = configResult.data.moduleType ?? 'regular';
            const radioId      = detectedType === 'template' ? 'typeTemplate' : 'typeRegular';
            document.getElementById(radioId).checked = true;

            const datalist = document.getElementById('templateSuggestions');
            datalist.innerHTML = '';
            (configResult.data.availableTemplates ?? []).forEach(tpl => {
                const opt = document.createElement('option');
                opt.value = tpl;
                datalist.appendChild(opt);
            });

            const saved     = configResult.data.templateName ?? '';
            const suggested = configResult.data.suggestedTemplate ?? '';
            document.getElementById('templateName').value = saved || suggested;
        } else {
            filterPatterns.value                          = '';
            document.getElementById('templateName').value = '';
            console.warn('Failed to load config:', configResult.error);
        }

        filterPatterns.disabled = false;
        this.updateTemplateNameVisibility();
        this.setDefaultDescription();
        this.updateSearchButton();
        document.getElementById('btnSaveFilters').removeAttribute('disabled');
    }

    incrementVersion(version) {
        const parts = version.split('.');
        if (parts.length !== 3 || parts.some(p => isNaN(parseInt(p)))) {
            return version;
        }
        parts[2] = String(parseInt(parts[2]) + 1);
        return parts.join('.');
    }

    async saveFilters(e, el) {
        this.hideError();
        el.disabled = true;

        const statusEl = document.getElementById('filterSaveStatus');
        statusEl.classList.add('d-none');

        const fd     = this.getFormData();
        const result = await this.runAction('saveConfig', {
            module:       fd.module,
            patterns:     document.getElementById('filterPatterns').value,
            templateName: fd.templateName,
        });

        el.disabled = false;

        if (!result.success) {
            this.showError(result.error);
            return;
        }

        statusEl.classList.remove('d-none');
        setTimeout(() => statusEl.classList.add('d-none'), 3000);
    }

    async search(e, el) {
        this.hideError();
        el.disabled    = true;
        el.textContent = 'Поиск...';

        const fd     = this.getFormData();
        const result = await this.runAction('search', {
            module:       fd.module,
            newVersion:   fd.newVersion,
            version:      fd.version,
            type:         fd.type,
            date:         fd.date,
            templateName: fd.templateName,
        });

        el.disabled    = false;
        el.textContent = 'Поиск';

        if (!result.success) {
            document.getElementById('btnArchive').disabled = true;
            this.showError(result.error);
            return;
        }

        this.renderFiles(result.data);
        document.getElementById('btnArchive').removeAttribute('disabled');
    }

    renderFiles(files) {
        const card  = document.getElementById('filesCard');
        const tbody = document.querySelector('#filesTable tbody');
        tbody.innerHTML = '';

        files.forEach((path, i) => {
            const tr = document.createElement('tr');

            const tdCheck = document.createElement('td');
            const cb      = document.createElement('input');
            cb.type       = 'checkbox';
            cb.className  = 'rb__checkbox';
            cb.checked    = true;
            cb.dataset.path = path;
            cb.addEventListener('change', () => this.updateSelectAll());
            tdCheck.appendChild(cb);

            const tdNum = document.createElement('td');
            tdNum.textContent = i + 1;

            const tdPath = document.createElement('td');
            const code   = document.createElement('code');
            code.textContent = path;
            tdPath.appendChild(code);

            tr.appendChild(tdCheck);
            tr.appendChild(tdNum);
            tr.appendChild(tdPath);
            tbody.appendChild(tr);
        });

        const master         = document.getElementById('selectAllFiles');
        master.checked       = true;
        master.indeterminate = false;

        card.classList.remove('d-none');
    }

    updateSelectAll() {
        const boxes   = [...document.querySelectorAll('#filesTable tbody input[type="checkbox"]')];
        const checked = boxes.filter(cb => cb.checked).length;
        const master  = document.getElementById('selectAllFiles');

        master.checked       = checked === boxes.length;
        master.indeterminate = checked > 0 && checked < boxes.length;

        document.getElementById('btnArchive').disabled = checked === 0;
    }

    onSelectAll(e, el) {
        document.querySelectorAll('#filesTable tbody input[type="checkbox"]')
            .forEach(cb => cb.checked = el.checked);
        this.updateSelectAll();
    }

    async prepareArchive(e, el) {
        this.hideError();
        el.disabled    = true;
        el.textContent = 'Создание архива...';

        const selected = [...document.querySelectorAll('#filesTable tbody input[type="checkbox"]')]
            .filter(cb => cb.checked)
            .map(cb => cb.dataset.path);

        const fd     = this.getFormData();
        const result = await this.runAction('prepareArchive', {
            module:        fd.module,
            newVersion:    fd.newVersion,
            version:       fd.version,
            type:          fd.type,
            date:          fd.date,
            description:   fd.description,
            templateName:  fd.templateName,
            selectedFiles: JSON.stringify(selected),
        });

        el.disabled    = false;
        el.textContent = 'Создать архив';

        if (!result.success) {
            this.showError(result.error);
            return;
        }

        const section    = document.getElementById('downloadSection');
        const link       = document.getElementById('downloadLink');
        link.href        = result.data;
        link.textContent = 'Скачать ' + result.data;
        section.classList.remove('d-none');
    }
}

BX.ready(() => new ReleaseBuilder());
