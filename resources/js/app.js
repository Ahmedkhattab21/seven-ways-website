import './bootstrap';

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
};

ready(() => {
    const appShell = document.querySelector('[data-app-shell]');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarCloseButtons = document.querySelectorAll('[data-sidebar-close]');
    const desktopBreakpoint = window.matchMedia('(min-width: 992px)');

    const syncSidebarState = () => {
        if (!appShell || !sidebarToggle) return;

        const expanded = desktopBreakpoint.matches
            ? !appShell.classList.contains('is-sidebar-collapsed')
            : appShell.classList.contains('is-sidebar-open');

        sidebarToggle.setAttribute('aria-expanded', String(expanded));
    };

    if (appShell && localStorage.getItem('sw-sidebar-collapsed') === 'true' && desktopBreakpoint.matches) {
        appShell.classList.add('is-sidebar-collapsed');
    }

    sidebarToggle?.addEventListener('click', () => {
        if (desktopBreakpoint.matches) {
            appShell?.classList.toggle('is-sidebar-collapsed');
            localStorage.setItem(
                'sw-sidebar-collapsed',
                String(appShell?.classList.contains('is-sidebar-collapsed')),
            );
        } else {
            appShell?.classList.toggle('is-sidebar-open');
        }

        syncSidebarState();
    });

    sidebarCloseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            appShell?.classList.remove('is-sidebar-open');
            syncSidebarState();
        });
    });

    document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
        const key = group.dataset.sidebarGroupKey;
        const toggle = group.querySelector('[data-sidebar-group-toggle]');
        const panel = group.querySelector('[data-sidebar-group-panel]');
        const containsActiveRoute = group.dataset.sidebarGroupActive === 'true';
        const storedState = localStorage.getItem(`sw-sidebar-group-${key}`);
        const initiallyOpen = containsActiveRoute
            || storedState === 'true'
            || (storedState === null && toggle?.getAttribute('aria-expanded') === 'true');

        panel?.toggleAttribute('hidden', !initiallyOpen);
        toggle?.setAttribute('aria-expanded', String(initiallyOpen));

        toggle?.addEventListener('click', () => {
            if (desktopBreakpoint.matches && appShell?.classList.contains('is-sidebar-collapsed')) {
                appShell.classList.remove('is-sidebar-collapsed');
                localStorage.setItem('sw-sidebar-collapsed', 'false');
            }

            const willOpen = panel?.hasAttribute('hidden');
            panel?.toggleAttribute('hidden');
            toggle.setAttribute('aria-expanded', String(willOpen));
            localStorage.setItem(`sw-sidebar-group-${key}`, String(willOpen));
            syncSidebarState();
        });
    });

    desktopBreakpoint.addEventListener('change', () => {
        appShell?.classList.remove('is-sidebar-open');
        syncSidebarState();
    });

    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        trigger?.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu?.hasAttribute('hidden');
            menu?.toggleAttribute('hidden');
            trigger.setAttribute('aria-expanded', String(willOpen));
        });

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target)) {
                menu?.setAttribute('hidden', '');
                trigger?.setAttribute('aria-expanded', 'false');
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = toggle.closest('.sw-field__control')?.querySelector('[data-password-input]');
            if (!input) return;

            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-label', showing ? 'إظهار كلمة المرور' : 'إخفاء كلمة المرور');
        });
    });

    document.querySelectorAll('[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-submit-button]');
            button?.setAttribute('disabled', '');
            button?.querySelector('[data-button-label]')?.setAttribute('hidden', '');
            button?.querySelector('[data-button-loading]')?.removeAttribute('hidden');
        });
    });

    document.querySelectorAll('[data-employee-skills-config]').forEach((configElement) => {
        const form = configElement.closest('form');
        const container = form?.querySelector('[data-employee-skills]');
        const emptyState = form?.querySelector('[data-employee-skills-empty]');
        const count = form?.querySelector('[data-employee-skills-count]');
        const warning = form?.querySelector('[data-employee-skills-warning]');
        const addButton = form?.querySelector('[data-add-employee-skill]');
        const branchSelect = form?.elements.namedItem('branch_id');
        const userSelect = form?.elements.namedItem('user_id');
        if (!form || !container || !branchSelect || !addButton) return;

        let config;
        try {
            config = JSON.parse(configElement.textContent);
        } catch {
            if (warning) {
                warning.textContent = 'تعذر تحميل بيانات مهارات الخدمات. أعد تحميل الصفحة وحاول مرة أخرى.';
                warning.removeAttribute('hidden');
            }
            addButton.disabled = true;
            return;
        }

        const services = Array.isArray(config.services) ? config.services : [];
        const levels = config.levels && typeof config.levels === 'object' ? config.levels : {};
        const errors = config.errors && typeof config.errors === 'object' ? config.errors : {};
        let rows = Array.isArray(config.initialRows) ? config.initialRows.map((row) => ({ ...row })) : [];

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[character]);

        const branchServices = () => {
            const branchId = Number(branchSelect.value);
            return services.filter((service) => service.branches.map(Number).includes(branchId));
        };

        const fieldError = (index, field) => {
            const messages = errors[`skills.${index}.${field}`];
            if (!Array.isArray(messages) || messages.length === 0) return '';

            return `<p class="sw-field__error">${escapeHtml(messages[0])}</p>`;
        };

        const serviceOptions = (selected, rowIndex) => {
            const usedByOtherRows = new Set(rows
                .filter((row, index) => index !== rowIndex && row.service_id)
                .map((row) => String(row.service_id)));

            return [
                '<option value="">اختر الخدمة</option>',
                ...branchServices()
                    .filter((service) => !usedByOtherRows.has(String(service.id))
                        || String(service.id) === String(selected ?? ''))
                    .map((service) => `<option value="${service.id}" ${String(service.id) === String(selected ?? '') ? 'selected' : ''}>${escapeHtml(service.label)}</option>`),
            ].join('');
        };

        const levelOptions = (selected) => Object.entries(levels)
            .map(([value, label]) => `<option value="${escapeHtml(value)}" ${value === (selected || 'intermediate') ? 'selected' : ''}>${escapeHtml(label)}</option>`)
            .join('');

        const showWarning = (message = '') => {
            if (!warning) return;
            warning.textContent = message;
            warning.toggleAttribute('hidden', message === '');
        };

        const filterUsers = () => {
            if (!userSelect?.options) return;
            const branchId = String(branchSelect.value);
            [...userSelect.options].forEach((option) => {
                if (!option.value) return;
                const allowed = (option.dataset.branches || '').split(',').includes(branchId);
                option.hidden = !allowed;
                option.disabled = !allowed;
                if (!allowed && option.selected) userSelect.value = '';
            });
        };

        const bindRow = (element, index) => {
            element.querySelector('[data-skill-service]')?.addEventListener('change', (event) => {
                const value = event.target.value;
                const duplicate = value && rows.some((row, rowIndex) => (
                    rowIndex !== index && String(row.service_id) === value
                ));
                if (duplicate) {
                    rows[index].service_id = '';
                    showWarning('لا يمكن تكرار نفس الخدمة داخل مهارات الموظف.');
                } else {
                    rows[index].service_id = value;
                    showWarning();
                }
                render();
            });

            element.querySelector('[data-skill-level]')?.addEventListener('change', (event) => {
                rows[index].skill_level = event.target.value;
            });
            element.querySelector('[data-skill-certified-at]')?.addEventListener('input', (event) => {
                rows[index].certified_at = event.target.value;
            });
            element.querySelector('[data-skill-expires-at]')?.addEventListener('input', (event) => {
                rows[index].certification_expires_at = event.target.value;
            });
            element.querySelector('[data-skill-primary]')?.addEventListener('change', (event) => {
                rows[index].is_primary = event.target.checked ? 1 : 0;
            });
            element.querySelector('[data-skill-active]')?.addEventListener('change', (event) => {
                rows[index].is_active = event.target.checked ? 1 : 0;
            });
            element.querySelector('[data-skill-notes]')?.addEventListener('input', (event) => {
                rows[index].notes = event.target.value;
            });
            element.querySelector('[data-remove-employee-skill]')?.addEventListener('click', () => {
                rows.splice(index, 1);
                showWarning();
                render();
            });
        };

        const render = () => {
            container.replaceChildren();
            rows.forEach((row, index) => {
                const element = document.createElement('section');
                element.className = 'employee-skill-row';
                element.dataset.employeeSkillRow = '';
                element.innerHTML = `
                    <div class="employee-skill-row__heading">
                        <strong>مهارة الخدمة رقم ${index + 1}</strong>
                        <button class="sw-button sw-button--danger" type="button" data-remove-employee-skill>حذف المهارة</button>
                    </div>
                    <label><span>الخدمة</span><select name="skills[${index}][service_id]" data-skill-service required>${serviceOptions(row.service_id, index)}</select>${fieldError(index, 'service_id')}</label>
                    <label><span>مستوى المهارة</span><select name="skills[${index}][skill_level]" data-skill-level required>${levelOptions(row.skill_level)}</select>${fieldError(index, 'skill_level')}</label>
                    <label><span>تاريخ الاعتماد</span><input type="date" name="skills[${index}][certified_at]" data-skill-certified-at value="${escapeHtml(row.certified_at)}">${fieldError(index, 'certified_at')}</label>
                    <label><span>انتهاء الاعتماد</span><input type="date" name="skills[${index}][certification_expires_at]" data-skill-expires-at value="${escapeHtml(row.certification_expires_at)}">${fieldError(index, 'certification_expires_at')}</label>
                    <label class="sw-check"><input type="hidden" name="skills[${index}][is_primary]" value="0"><input type="checkbox" name="skills[${index}][is_primary]" data-skill-primary value="1" ${Number(row.is_primary) ? 'checked' : ''}> مهارة أساسية</label>
                    <label class="sw-check"><input type="hidden" name="skills[${index}][is_active]" value="0"><input type="checkbox" name="skills[${index}][is_active]" data-skill-active value="1" ${row.is_active === undefined || Number(row.is_active) ? 'checked' : ''}> مهارة نشطة</label>
                    <label class="employee-skill-row__notes"><span>ملاحظات</span><input name="skills[${index}][notes]" data-skill-notes value="${escapeHtml(row.notes)}" maxlength="2000">${fieldError(index, 'notes')}</label>
                `;
                container.append(element);
                bindRow(element, index);
            });

            emptyState?.toggleAttribute('hidden', rows.length !== 0);
            if (count) count.textContent = String(rows.length);
        };

        addButton.addEventListener('click', () => {
            rows.push({
                service_id: '',
                skill_level: 'intermediate',
                is_primary: 0,
                is_active: 1,
                certified_at: null,
                certification_expires_at: null,
                notes: null,
            });
            showWarning();
            render();
        });

        branchSelect.addEventListener('change', () => {
            const availableIds = new Set(branchServices().map((service) => String(service.id)));
            let removedUnavailableService = false;
            rows = rows.map((row) => {
                if (!row.service_id || availableIds.has(String(row.service_id))) return row;
                removedUnavailableService = true;
                return { ...row, service_id: '' };
            });
            showWarning(removedUnavailableService
                ? 'تم إزالة خدمة غير متاحة في الفرع المحدد.'
                : '');
            filterUsers();
            render();
        });

        filterUsers();
        render();
    });

    document.querySelectorAll('[data-quotation-customer]').forEach((customerSelect) => {
        const form = customerSelect.closest('form');
        const vehicleSelect = form?.querySelector('[data-quotation-vehicle]');
        const emptyOption = vehicleSelect?.querySelector('[data-empty-vehicle]');
        const emptyMessage = form?.querySelector('[data-quotation-vehicle-empty]');

        if (!vehicleSelect || !emptyOption) return;

        const filterVehicles = () => {
            const customerId = customerSelect.value;
            const vehicleOptions = [...vehicleSelect.querySelectorAll('option[data-customer-id]')];
            let visibleCount = 0;

            vehicleOptions.forEach((option) => {
                const visible = option.dataset.customerId === customerId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible) visibleCount += 1;
            });

            const selectedOption = vehicleSelect.selectedOptions[0];
            if (!selectedOption?.dataset.customerId || selectedOption.dataset.customerId !== customerId) {
                const firstVisible = vehicleOptions.find((option) => !option.hidden);
                vehicleSelect.value = firstVisible?.value ?? '';
            }

            emptyOption.hidden = visibleCount > 0;
            emptyOption.disabled = visibleCount > 0;
            if (visibleCount === 0) vehicleSelect.value = '';
            emptyMessage?.toggleAttribute('hidden', visibleCount > 0);
        };

        customerSelect.addEventListener('change', filterVehicles);
        filterVehicles();
    });

    document.querySelectorAll('[data-quotation-builder]').forEach((form) => {
        const itemsContainer = form.querySelector('[data-quotation-items]');
        const itemTemplate = document.querySelector('[data-quotation-item-template]');
        const addButton = form.querySelector('[data-add-quotation-item]');
        const submitButton = form.querySelector('[data-quotation-submit]');
        const previewStatus = form.querySelector('[data-preview-status]');
        const previewError = form.querySelector('[data-preview-error]');
        const headerDiscountType = form.querySelector('[data-header-discount-type]');
        const headerDiscountValue = form.querySelector('[data-header-discount-value]');
        const priceSourceLabels = {
            branch_product_price: 'سعر الفرع',
            unified_product_price: 'السعر الموحد لكل الفروع',
            product_promotion: 'سعر بعد عرض ترويجي',
        };
        let previewTimer;
        let previewRequest;

        if (!itemsContainer || !itemTemplate) return;

        const money = (value, currency) => `${Number(value ?? 0).toFixed(2)} ${currency ?? ''}`.trim();

        const reindexItems = () => {
            itemsContainer.querySelectorAll('[data-quotation-item]').forEach((item, index) => {
                item.querySelector('[data-item-number]').textContent = String(index + 1);
                item.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(/items\[[^\]]+]/, `items[${index}]`);
                });
            });

            const onlyOne = itemsContainer.querySelectorAll('[data-quotation-item]').length === 1;
            itemsContainer.querySelectorAll('[data-remove-quotation-item]').forEach((button) => {
                button.disabled = onlyOne;
            });
        };

        const syncDiscount = (item, clear = false) => {
            const type = item.querySelector('[data-item-discount-type]');
            const valueLabel = item.querySelector('[data-item-discount-value]');
            const value = valueLabel?.querySelector('input');
            const enabled = Boolean(type?.value);
            valueLabel?.toggleAttribute('hidden', !enabled);
            if (value) {
                value.disabled = !enabled;
                if (!enabled && clear) value.value = '0';
            }
        };

        const syncProduct = (item) => {
            const option = item.querySelector('[data-item-reference]')?.selectedOptions[0];
            const unit = item.querySelector('[data-item-unit]');
            if (unit) unit.textContent = option?.dataset.saleUnit || '—';
        };

        const refreshProducts = async () => {
            const branchId = form.elements.namedItem('branch_id')?.value;
            const quotationDate = form.elements.namedItem('quotation_date')?.value;
            if (!form.dataset.productsUrl || !branchId || !quotationDate) return;
            const url = new URL(form.dataset.productsUrl, window.location.origin);
            url.searchParams.set('branch_id', branchId);
            url.searchParams.set('quotation_date', quotationDate);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            itemsContainer.querySelectorAll('[data-item-reference]').forEach((select) => {
                const selected = select.value;
                select.replaceChildren(new Option('اختر المنتج', ''));
                payload.products.forEach((product) => {
                    const option = new Option(
                        `${product.sku} — ${product.name} — المتاح ${Number(product.available_stock).toFixed(2)}`,
                        product.id
                    );
                    option.dataset.saleUnit = product.sale_unit || '';
                    option.dataset.availableStock = product.available_stock;
                    select.add(option);
                });
                select.value = [...select.options].some((option) => option.value === selected) ? selected : '';
                select.closest('[data-quotation-item]')?.querySelector('[data-product-empty]')
                    ?.toggleAttribute('hidden', payload.products.length > 0);
                syncProduct(select.closest('[data-quotation-item]'));
            });
        };

        const canPreview = () => {
            const requiredHeader = ['branch_id', 'customer_id', 'vehicle_id', 'currency_id', 'quotation_date'];
            if (requiredHeader.some((name) => !form.elements.namedItem(name)?.value)) return false;

            return [...itemsContainer.querySelectorAll('[data-quotation-item]')].every((item) => {
                const reference = item.querySelector('[data-item-reference]');
                return Boolean(reference?.value && item.querySelector('[data-item-quantity]')?.value);
            });
        };

        const clearErrors = () => {
            previewError?.setAttribute('hidden', '');
            itemsContainer.querySelectorAll('[data-item-error]').forEach((error) => error.setAttribute('hidden', ''));
        };

        const showPreviewError = (message, errors = {}) => {
            if (previewError) {
                previewError.textContent = message;
                previewError.removeAttribute('hidden');
            }
            Object.entries(errors).forEach(([key, messages]) => {
                const match = key.match(/^items\.(\d+)\./);
                const itemError = match
                    ? itemsContainer.querySelectorAll('[data-quotation-item]')[Number(match[1])]
                        ?.querySelector('[data-item-error]')
                    : null;
                if (itemError) {
                    itemError.textContent = Array.isArray(messages) ? messages[0] : messages;
                    itemError.removeAttribute('hidden');
                }
            });
            previewStatus.textContent = 'تعذر حساب السعر';
            submitButton.disabled = true;
        };

        const applyPreview = (payload) => {
            payload.items.forEach((row, index) => {
                const item = itemsContainer.querySelectorAll('[data-quotation-item]')[index];
                if (!item) return;
                const currency = payload.summary.currency_code;
                item.querySelector('[data-item-base-unit-price]').textContent = money(row.base_unit_price, currency);
                item.querySelector('[data-item-unit-price]').textContent = money(row.unit_price, currency);
                item.querySelector('[data-item-price-source]').textContent =
                    priceSourceLabels[row.price_source] ?? 'سعر محسوب من النظام';
                item.querySelector('[data-item-discount]').textContent = money(row.item_discount_amount, currency);
                item.querySelector('[data-item-net]').textContent = money(row.net_amount, currency);
                item.querySelector('[data-item-tax]').textContent = money(row.tax_amount, currency);
                item.querySelector('[data-item-total]').textContent = money(row.line_total, currency);
                if (item.querySelector('[data-item-unit]')) item.querySelector('[data-item-unit]').textContent = row.sale_unit || '—';
                const warning = item.querySelector('[data-item-warning]');
                warning.textContent = row.warnings?.join(' ') ?? '';
                warning.toggleAttribute('hidden', !row.warnings?.length);
            });
            Object.entries(payload.summary).forEach(([key, value]) => {
                const target = form.querySelector(`[data-summary="${key}"]`);
                if (target) target.textContent = money(value, payload.summary.currency_code);
            });
            Object.entries(payload.summary).forEach(([key, value]) => {
                const target = form.querySelector(`[data-summary-plain="${key}"]`);
                if (target) target.textContent = value ?? '—';
            });
            previewStatus.textContent = 'تم الحساب من الخادم';
            submitButton.disabled = false;
        };

        const requestPreview = async () => {
            clearErrors();
            if (!canPreview()) {
                previewStatus.textContent = 'أكمل بيانات العناصر لحساب السعر';
                submitButton.disabled = true;
                return;
            }

            previewRequest?.abort();
            previewRequest = new AbortController();
            previewStatus.textContent = 'جارٍ حساب السعر...';
            submitButton.disabled = true;
            const data = new FormData(form);
            data.delete('_method');

            try {
                const response = await fetch(form.dataset.previewUrl, {
                    method: 'POST',
                    body: data,
                    headers: { Accept: 'application/json' },
                    signal: previewRequest.signal,
                });
                const payload = await response.json();
                if (!response.ok) {
                    showPreviewError(payload.message ?? 'تعذر حساب السعر للعناصر الحالية.', payload.errors);
                    return;
                }
                applyPreview(payload);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    showPreviewError('تعذر الاتصال بالخادم لحساب السعر. أعد المحاولة.');
                }
            }
        };

        const schedulePreview = () => {
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(requestPreview, 250);
        };

        const bindItem = (item) => {
            item.querySelector('[data-item-reference]')?.addEventListener('change', () => {
                syncProduct(item);
                schedulePreview();
            });
            item.querySelector('[data-item-discount-type]')?.addEventListener('change', () => {
                syncDiscount(item, true);
                schedulePreview();
            });
            item.querySelector('[data-remove-quotation-item]')?.addEventListener('click', () => {
                if (itemsContainer.querySelectorAll('[data-quotation-item]').length <= 1) return;
                const hasEnteredData = [...item.querySelectorAll('input, select')].some((field) => {
                    if (field.matches('[data-item-quantity]')) return false;
                    return field.type === 'checkbox' ? field.checked : Boolean(field.value);
                });
                if (hasEnteredData && !window.confirm('هل تريد حذف هذا المنتج والبيانات المدخلة به؟')) return;
                item.remove();
                reindexItems();
                schedulePreview();
            });
            syncProduct(item);
            syncDiscount(item);
        };

        addButton?.addEventListener('click', () => {
            const index = itemsContainer.querySelectorAll('[data-quotation-item]').length;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = itemTemplate.innerHTML.replaceAll('__INDEX__', String(index)).trim();
            const item = wrapper.firstElementChild;
            itemsContainer.append(item);
            bindItem(item);
            reindexItems();
            schedulePreview();
        });

        headerDiscountType?.addEventListener('change', () => {
            const enabled = Boolean(headerDiscountType.value);
            const input = headerDiscountValue?.querySelector('input');
            headerDiscountValue?.toggleAttribute('hidden', !enabled);
            if (input) {
                input.disabled = !enabled;
                if (!enabled) input.value = '0';
            }
            schedulePreview();
        });

        form.addEventListener('input', (event) => {
            if (event.target.matches('input, select')) schedulePreview();
        });
        form.addEventListener('change', (event) => {
            if (event.target.matches('input, select')) schedulePreview();
        });
        form.elements.namedItem('branch_id')?.addEventListener('change', refreshProducts);
        form.elements.namedItem('quotation_date')?.addEventListener('change', refreshProducts);
        form.addEventListener('submit', () => {
            if (submitButton.disabled) return;
            submitButton.disabled = true;
            submitButton.textContent = 'جارٍ حفظ المسودة...';
        });

        itemsContainer.querySelectorAll('[data-quotation-item]').forEach(bindItem);
        reindexItems();
        if (headerDiscountType) headerDiscountType.dispatchEvent(new Event('change'));
        schedulePreview();
    });

    document.querySelectorAll('[data-package-form]').forEach((form) => {
        const container = form.querySelector('[data-package-items]');
        const template = document.querySelector('[data-package-item-template]');
        const addButton = form.querySelector('[data-add-package-service]');
        const branch = form.elements.namedItem('branch_id');
        const approvedPrice = form.elements.namedItem('price');
        const duplicateMessage = form.querySelector('[data-package-duplicate]');
        const missingPricesMessage = form.querySelector('[data-package-missing-prices]');
        if (!container || !template) return;

        const rows = () => [...container.querySelectorAll('[data-package-item]')];
        const reindex = () => rows().forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/items\[[^\]]+]/, `items[${index}]`);
            });
            const remove = row.querySelector('[data-remove-package-service]');
            if (remove) remove.disabled = rows().length === 1;
        });
        const calculate = () => {
            let total = 0;
            const selected = [];
            let missingPrice = false;
            rows().forEach((row) => {
                const option = row.querySelector('[data-package-service]')?.selectedOptions[0];
                const quantity = Number(row.querySelector('[data-package-quantity]')?.value || 0);
                if (option?.value) selected.push(option.value);
                const prices = option?.dataset.prices ? JSON.parse(option.dataset.prices) : {};
                const price = prices[branch?.value];
                if (option?.value && (price === undefined || price === null || price === '')) {
                    missingPrice = true;
                } else {
                    total += Number(price || 0) * quantity;
                }
            });
            const duplicate = new Set(selected).size !== selected.length;
            duplicateMessage?.toggleAttribute('hidden', !duplicate);
            const approved = Number(approvedPrice?.value || 0);
            const saving = missingPrice ? null : Math.max(0, total - approved);
            const percent = !missingPrice && total > 0 ? (saving / total) * 100 : null;
            form.querySelector('[data-package-standalone]')?.replaceChildren(missingPrice ? '—' : total.toFixed(2));
            form.querySelector('[data-package-suggested]')?.replaceChildren(missingPrice ? '—' : total.toFixed(2));
            form.querySelector('[data-package-approved]')?.replaceChildren(approved.toFixed(2));
            form.querySelector('[data-package-saving]')?.replaceChildren(saving === null ? '—' : saving.toFixed(2));
            form.querySelector('[data-package-saving-percent]')?.replaceChildren(percent === null ? '—' : percent.toFixed(2));
            missingPricesMessage?.toggleAttribute('hidden', !missingPrice);
        };
        const bind = (row) => {
            row.querySelector('[data-remove-package-service]')?.addEventListener('click', () => {
                if (rows().length === 1) return;
                row.remove();
                reindex();
                calculate();
            });
            row.querySelectorAll('select, input').forEach((field) => {
                field.addEventListener('input', calculate);
                field.addEventListener('change', calculate);
            });
        };
        addButton?.addEventListener('click', () => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(rows().length)).trim();
            const row = wrapper.firstElementChild;
            container.append(row);
            bind(row);
            reindex();
            calculate();
        });
        form.addEventListener('submit', (event) => {
            const ids = rows().map((row) => row.querySelector('[data-package-service]')?.value).filter(Boolean);
            if (new Set(ids).size !== ids.length) {
                event.preventDefault();
                duplicateMessage?.removeAttribute('hidden');
            }
        });
        rows().forEach(bind);
        branch?.addEventListener('change', calculate);
        approvedPrice?.addEventListener('input', calculate);
        reindex();
        calculate();
    });

    document.querySelectorAll('[data-sales-invoice-form]').forEach((form) => {
        const container = form.querySelector('[data-invoice-items]');
        const template = form.querySelector('[data-invoice-item-template]');
        const customer = form.querySelector('[data-invoice-customer]');
        const vehicle = form.querySelector('[data-invoice-vehicle]');
        let itemIndex = 0;

        const filterVehicles = () => {
            const selectedCustomer = customer?.value;
            vehicle?.querySelectorAll('option[data-customer-id]').forEach((option) => {
                option.hidden = Boolean(selectedCustomer) && option.dataset.customerId !== selectedCustomer;
                option.disabled = option.hidden;
            });
            if (vehicle?.selectedOptions[0]?.disabled) vehicle.value = '';
        };
        const bindItem = (row) => {
            const type = row.querySelector('[data-invoice-item-type]');
            const sync = () => {
                row.querySelectorAll('[data-invoice-reference]').forEach((field) => {
                    const reference = field.dataset.invoiceReference;
                    const visible = reference === type.value || (reference === 'warehouse' && type.value === 'product');
                    field.hidden = !visible;
                    field.querySelectorAll('select, input').forEach((input) => {
                        input.disabled = !visible;
                        input.required = visible;
                    });
                });
            };
            type.addEventListener('change', sync);
            row.querySelector('[data-remove-invoice-item]')?.addEventListener('click', () => {
                if (container.children.length > 1) row.remove();
            });
            sync();
        };
        const addItem = () => {
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(itemIndex++)).trim();
            const row = wrapper.firstElementChild;
            container.append(row);
            bindItem(row);
        };

        customer?.addEventListener('change', filterVehicles);
        form.querySelector('[data-add-invoice-item]')?.addEventListener('click', addItem);
        filterVehicles();
        addItem();
    });

    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            document.getElementById(trigger.dataset.modalOpen)?.removeAttribute('hidden');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            document.getElementById(trigger.dataset.modalClose)?.setAttribute('hidden', '');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.sw-modal:not([hidden])').forEach((modal) => modal.setAttribute('hidden', ''));
            appShell?.classList.remove('is-sidebar-open');
            syncSidebarState();
        }
    });

    syncSidebarState();
});
