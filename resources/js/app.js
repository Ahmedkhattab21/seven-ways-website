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
            service_price: 'سعر حسب الفرع وبيانات السيارة',
            branch_default: 'سعر الفرع',
            package_price: 'سعر الباقة',
            product_price: 'سعر المنتج',
            promotion: 'سعر بعد عرض ترويجي',
            manual: 'سعر يدوي',
            custom_quote: 'سعر عنصر مخصص',
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

        const syncManualPrice = (item, clear = false) => {
            const type = item.querySelector('[data-item-type]')?.value;
            const toggle = item.querySelector('[data-manual-price-toggle]');
            const toggleLabel = item.querySelector('[data-manual-price-toggle-label]');
            const fieldLabel = item.querySelector('[data-manual-price-field]');
            const input = fieldLabel?.querySelector('input');
            if (!input) return;

            const custom = type === 'custom';
            toggleLabel?.toggleAttribute('hidden', custom);
            const enabled = custom || Boolean(toggle?.checked);
            fieldLabel.toggleAttribute('hidden', !enabled);
            input.disabled = !enabled;
            input.required = custom;
            if (!enabled && clear) input.value = '';
        };

        const syncItemType = (item, clear = false) => {
            const type = item.querySelector('[data-item-type]')?.value ?? 'service';
            const labels = {
                service: ['الخدمة', 'خصم هذه الخدمة'],
                package: ['الباقة', 'خصم هذه الباقة'],
                product: ['المنتج', 'خصم هذا المنتج'],
                custom: ['البند المخصص', 'خصم البند المخصص'],
            };
            item.querySelector('[data-item-title]').textContent = labels[type][0];
            item.querySelector('[data-item-discount-title]').textContent = labels[type][1];
            item.querySelectorAll('[data-item-field]').forEach((label) => {
                const visible = label.dataset.itemField === type;
                const field = label.querySelector('select, input');
                label.toggleAttribute('hidden', !visible);
                if (field) {
                    field.disabled = !visible;
                    field.required = visible;
                    if (!visible && clear) field.value = '';
                }
            });
            const description = item.querySelector('[data-item-description]');
            if (description) description.required = type === 'custom';
            item.querySelector('[data-package-details]')?.toggleAttribute('hidden', type !== 'package');
            syncManualPrice(item, clear);
        };

        const filterPackages = () => {
            const branchId = form.elements.namedItem('branch_id')?.value;
            const vehicleSizeId = form.querySelector('[data-quotation-vehicle]')?.selectedOptions[0]?.dataset.vehicleSizeId || '';
            const priceDate = form.elements.namedItem('quotation_date')?.value;
            itemsContainer.querySelectorAll('[data-item-field="package"] select').forEach((select) => {
                let availableCount = 0;
                [...select.querySelectorAll('option[data-package-availability]')].forEach((option) => {
                    const prices = JSON.parse(option.dataset.packageAvailability || '[]');
                    const visible = prices.some((price) =>
                        String(price.branch_id) === String(branchId)
                        && (!price.vehicle_size_id || String(price.vehicle_size_id) === String(vehicleSizeId))
                        && (!price.effective_from || price.effective_from <= priceDate)
                        && (!price.effective_to || price.effective_to >= priceDate)
                    );
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible) availableCount += 1;
                });
                if (select.selectedOptions[0]?.disabled) select.value = '';
                const message = select.closest('[data-item-field="package"]')?.querySelector('[data-package-empty]');
                if (message) {
                    message.textContent = vehicleSizeId
                        ? 'لا توجد باقة متاحة بسعر ساري لهذا الفرع وحجم السيارة في تاريخ العرض.'
                        : 'السيارة المحددة لا تحتوي على حجم، بينما الباقات الحالية مسعّرة حسب حجم السيارة. حدّث حجم السيارة أو أضف سعرًا للباقة يشمل كل الأحجام.';
                    message.toggleAttribute('hidden', availableCount > 0);
                }
            });
        };

        const canPreview = () => {
            const requiredHeader = ['branch_id', 'customer_id', 'vehicle_id', 'currency_id', 'quotation_date'];
            if (requiredHeader.some((name) => !form.elements.namedItem(name)?.value)) return false;

            return [...itemsContainer.querySelectorAll('[data-quotation-item]')].every((item) => {
                const type = item.querySelector('[data-item-type]')?.value;
                const reference = item.querySelector(`[data-item-field="${type}"] [data-item-reference]`);
                if (type === 'custom') {
                    return Boolean(item.querySelector('[data-item-description]')?.value
                        && item.querySelector('[data-manual-price-field] input')?.value);
                }

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
                item.querySelector('[data-item-gross]').textContent = money(row.gross_amount, currency);
                item.querySelector('[data-item-discount]').textContent = money(row.item_discount_amount, currency);
                item.querySelector('[data-item-net]').textContent = money(row.net_amount, currency);
                item.querySelector('[data-item-tax]').textContent = money(row.tax_amount, currency);
                item.querySelector('[data-item-total]').textContent = money(row.line_total, currency);
                item.querySelector('[data-item-duration]').textContent = row.estimated_duration_minutes ?? '—';
                const packageDetails = item.querySelector('[data-package-details]');
                if (packageDetails) {
                    packageDetails.querySelector('[data-package-services]').textContent = row.package_services?.length
                        ? row.package_services.map((service) => `${service.name} × ${Number(service.quantity)}`).join('، ')
                        : '—';
                    packageDetails.querySelector('[data-package-standalone]').textContent =
                        row.standalone_services_total === null ? 'غير متاح' : money(row.standalone_services_total, currency);
                    packageDetails.querySelector('[data-package-saving]').textContent =
                        row.package_savings === null ? 'غير متاح' : money(row.package_savings, currency);
                }
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
            filterPackages();
            window.clearTimeout(previewTimer);
            previewTimer = window.setTimeout(requestPreview, 250);
        };

        const bindItem = (item) => {
            item.querySelector('[data-item-type]')?.addEventListener('change', () => {
                syncItemType(item, true);
                schedulePreview();
            });
            item.querySelector('[data-manual-price-toggle]')?.addEventListener('change', () => {
                syncManualPrice(item, true);
                schedulePreview();
            });
            item.querySelector('[data-item-discount-type]')?.addEventListener('change', () => {
                syncDiscount(item, true);
                schedulePreview();
            });
            item.querySelector('[data-remove-quotation-item]')?.addEventListener('click', () => {
                if (itemsContainer.querySelectorAll('[data-quotation-item]').length <= 1) return;
                const hasEnteredData = [...item.querySelectorAll('input, select')].some((field) => {
                    if (field.matches('[data-item-type], [data-item-quantity]')) return false;
                    return field.type === 'checkbox' ? field.checked : Boolean(field.value);
                });
                if (hasEnteredData && !window.confirm('هل تريد حذف هذا العنصر والبيانات المدخلة به؟')) return;
                item.remove();
                reindexItems();
                schedulePreview();
            });
            syncItemType(item, true);
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
            filterPackages();
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
        form.addEventListener('submit', () => {
            if (submitButton.disabled) return;
            submitButton.disabled = true;
            submitButton.textContent = 'جارٍ حفظ المسودة...';
        });

        itemsContainer.querySelectorAll('[data-quotation-item]').forEach(bindItem);
        reindexItems();
        filterPackages();
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
