class GUI_InvestmentCalculator extends GUI_Module
{
    init()
    {
        this.workspaceStorageKey = 'realestateinvestment.workspaceKey.v1';
        this.workspaceKey = this.initWorkspaceKey();
        this.draftKey = `realestateinvestment.scenarioDraft.v1.${this.workspaceKey}`;
        this.sectionStateKey = 'realestateinvestment.sectionState.v1';
        this.restoringDraft = false;
        this.calculateTimer = null;
        this.calculateSequence = 0;
        this.scenarioRows = [];
        this.special7bRules = [
            {from: '2018-09-01', until: '2022-01-01', constructionCostLimitPerSqm: 3000, specialAfaLimitPerSqm: 2000, ratePercent: 5, years: 4},
            {from: '2023-01-01', until: '2029-10-01', constructionCostLimitPerSqm: 5200, specialAfaLimitPerSqm: 4000, ratePercent: 5, years: 4},
        ];
        this.special7bCostTriggerPaths = [
            'property.livingArea',
            'property.apartmentPurchasePrice',
            'property.otherPurchasePrice',
            'property.furniturePurchasePrice',
            'property.landSharePercent',
            'property.buildingSharePercent',
            'acquisitionCosts.realEstateTransferTaxPercent',
            'acquisitionCosts.notaryPercent',
            'acquisitionCosts.landRegisterPurchasePercent',
            'acquisitionCosts.notaryLandRegisterTaxTreatment',
            'acquisitionCosts.brokerPercent',
            'acquisitionCosts.otherAcquisitionCosts',
            'depreciation.buildingBasis',
            'depreciation.special7bArea',
            'depreciation.special7bLimitPerSqm',
            'depreciation.special7bActualConstructionCostPerSqm',
        ];
        this.form = this.element('[data-role="scenario-form"]');
        this.summary = this.element('[data-role="summary"]');
        this.purchaseBreakdown = this.element('[data-role="purchase-breakdown"]');
        this.acquisitionBreakdown = this.element('[data-role="acquisition-breakdown"]');
        this.financingBreakdown = this.element('[data-role="financing-breakdown"]');
        this.totalBreakdown = this.element('[data-role="total-breakdown"]');
        this.depreciationBasisBreakdown = this.element('[data-role="depreciation-basis-breakdown"]');
        this.calculationBreakdown = this.element('[data-role="calculation-breakdown"]');
        this.scaleLegend = this.element('[data-role="scale-legend"]');
        this.warnings = this.element('[data-role="warnings"]');
        this.formulas = this.element('[data-role="formulas"]');
        this.rowCount = this.element('[data-role="row-count"]');
        this.parkingList = this.element('[data-role="parking-list"]');
        this.constructionInterestList = this.element('[data-role="construction-interest-list"]');
        this.loanList = this.element('[data-role="loan-list"]');
        this.scenarioGroupList = this.element('[data-role="scenario-group-list"]');
        this.scenarioList = this.element('[data-role="scenario-list"]');
        this.comparisonSection = this.element('[data-role="comparison-section"]');
        this.comparisonBody = this.element('[data-role="comparison-body"]');
        this.comparisonToggle = this.element('[data-role="comparison-toggle"]');

        this.initTables();
        this.bindControls();
        this.prepareStaticInputs();
        this.initSectionToggles();
        this.resetStaticInputs();
        const draft = this.loadDraft();
        if(draft) {
            this.applyScenario(draft);
        } else {
            this.addDefaultRows();
            this.syncDepreciationStartDefaults(true);
            this.syncSpecial7bDefaults(false);
        }
        this.updateTaxModeState();
        this.syncSpecial7bCalculatedCosts();
        this.calculate();
        this.refreshScenarioList().then(() => this.importSharedScenarioFromUrl());
    }

    initTables()
    {
        this.yearTable = new Tabulator(this.element('[data-role="year-grid"]'), {
            height: '520px',
            layout: 'fitDataStretch',
            placeholder: 'Keine Berechnung',
            columns: [
                {title: 'Jahr', field: 'year', frozen: true, width: 80, bottomCalc: () => 'Summe'},
                {title: 'Monate', field: 'rentedMonths', hozAlign: 'right', width: 90, bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.dec(cell.getValue(), 0)},
                {title: 'Miete', field: 'rent', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'laufende Ausgaben', field: 'expenses', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'abzugsf. laufende Kosten', field: 'deductibleOperatingExpenses', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Operativer CF', field: 'operatingCashflow', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Darlehenszinsen', field: 'interest', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Tilgung', field: 'repayment', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Annuität', field: 'annuity', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Restschuld', field: 'remainingDebt', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: (values, data) => this.lastValue(data, 'remainingDebt'), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Bauzeitzinsen brutto', field: 'constructionInterest', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'BZZ/Kreditkosten/Erwerbs-WK', field: 'deductibleExpenses', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Werbungskosten ohne AfA', field: 'advertisingCostsWithoutDepreciation', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'AfA degressiv', field: 'depreciationDegressive', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'AfA linear', field: 'depreciationLinear', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'AfA 7b', field: 'depreciation7b', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'AfA Möbel/Küche', field: 'depreciationFurniture', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'AfA Stellplatz', field: 'depreciationParking', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'AfA gesamt', field: 'depreciationTotal', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'steuerliche Abzüge gesamt', field: 'taxDeductionsTotal', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Einkünfte VuV', field: 'rentalTaxableIncome', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'zvE vor', field: 'taxableIncomeBefore', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'zvE nach', field: 'taxableIncomeAfter', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'ESt vor', field: 'tariffIncomeTaxBefore', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'ESt nach', field: 'tariffIncomeTaxAfter', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Soli vor', field: 'solidaritySurchargeBefore', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Soli nach', field: 'solidaritySurchargeAfter', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Kirchensteuer vor', field: 'churchTaxBefore', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Kirchensteuer nach', field: 'churchTaxAfter', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Steuer gesamt vor', field: 'incomeTaxBefore', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Steuer gesamt nach', field: 'incomeTaxAfter', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Steuerwirkung', field: 'taxEffect', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'eff. Steuerwirkung %', field: 'effectiveTaxRate', hozAlign: 'right', formatter: cell => this.pct(cell.getValue())},
                {title: 'CF vor Steuer', field: 'netCashflowBeforeTax', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'CF nach Steuer vor Auto-ST', field: 'netCashflowAfterTaxBeforeAutoSpecialRepayment', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Auto-Sondertilgung', field: 'autoSpecialRepayment', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Auto-ST Zielkredit', field: 'autoSpecialRepaymentTarget', minWidth: 180},
                {title: 'CF nach Steuer', field: 'netCashflowAfterTax', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Verkaufspreis', field: 'salePrice', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Erlös nach Schuld', field: 'saleProceedsAfterDebt', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'CF inkl. Verkauf', field: 'netCashflowIncludingSale', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue())},
                {title: 'Opportunitätszins', field: 'opportunityInterest', hozAlign: 'right', formatter: cell => this.eur(cell.getValue()), bottomCalc: values => this.sum(values), bottomCalcFormatter: cell => this.eur(cell.getValue()), headerTooltip: 'Zins-/Opportunitätseffekt dieses Jahres-Cashflows bis zum Verkaufsjahr mit dem gewählten Diskontsatz.'},
            ],
        });

        this.comparisonTable = new Tabulator(this.element('[data-role="comparison-grid"]'), {
            height: '260px',
            layout: 'fitColumns',
            placeholder: 'Kein Vergleich',
            columns: [
                {title: 'Szenario', field: 'scenarioName', minWidth: 180},
                {title: 'Nettoeffekt', field: 'netWorthEffect', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'Hebel', field: 'leverageEfficiency', hozAlign: 'right', formatter: cell => this.pct(cell.getValue())},
                {title: 'NPV', field: 'npv', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'BW-Hebel', field: 'npvLeverageEfficiency', hozAlign: 'right', formatter: cell => this.pct(cell.getValue())},
                {title: 'FV kons.', field: 'futureValueConservative', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'FV-Hebel kons.', field: 'fvLeverageConservative', hozAlign: 'right', formatter: cell => this.pct(cell.getValue())},
                {title: 'FV liqu.', field: 'futureValueLiquidity', hozAlign: 'right', formatter: cell => this.eur(cell.getValue())},
                {title: 'FV-Hebel liqu.', field: 'fvLeverageLiquidity', hozAlign: 'right', formatter: cell => this.pct(cell.getValue())},
                {title: 'DSCR', field: 'dscr', hozAlign: 'right', formatter: cell => this.dec(cell.getValue(), 2)},
                {title: 'Debt Yield', field: 'debtYield', hozAlign: 'right', formatter: cell => this.pct(cell.getValue())},
            ],
        });
    }

    bindControls()
    {
        this.element('[data-role="new-scenario"]').addEventListener('click', () => this.newScenario());
        this.element('[data-role="calculate"]').addEventListener('click', () => this.calculate());
        this.element('[data-role="save"]').addEventListener('click', () => this.saveScenario());
        this.element('[data-role="rename-scenario"]').addEventListener('click', () => this.renameScenario());
        this.element('[data-role="delete-scenario"]').addEventListener('click', () => this.deleteScenario());
        this.element('[data-role="load"]').addEventListener('click', () => this.loadSelectedScenario());
        this.element('[data-role="copy-workspace-link"]').addEventListener('click', () => this.copyWorkspaceLink());
        this.element('[data-role="share-scenario"]').addEventListener('click', () => this.shareScenario());
        this.scenarioGroupList.addEventListener('change', () => this.renderScenarioOptions());
        this.scenarioList.addEventListener('change', () => {
            if(this.scenarioList.value) {
                this.loadSelectedScenario();
            }
        });
        this.element('[data-role="compare"]').addEventListener('click', () => this.compareScenarios());
        this.comparisonToggle.addEventListener('click', () => this.setComparisonCollapsed(!this.comparisonSection.classList.contains('is-collapsed')));
        this.element('[data-role="add-parking"]').addEventListener('click', () => this.addParkingRow({label: 'Stellplatz', buildingSharePercent: 80, landSharePercent: 20, depreciable: true, includedInPurchasePrice: true}));
        this.element('[data-role="add-construction-interest"]').addEventListener('click', () => this.addConstructionInterestRow({label: 'Bauzeitzinsen', year: Number(this.value('property.purchaseYear') || 2026), deductible: true}));
        this.element('[data-role="add-loan"]').addEventListener('click', () => this.addLoanRow({name: 'Darlehen', startYear: Number(this.value('property.purchaseYear') || 2026), startMonth: Number(this.value('property.purchaseMonth') || 1), fixedInterestYears: 10, constantAnnuity: true, redeemOnSale: true}));
        this.getRootElement().addEventListener('keydown', event => this.preventInvalidIntegerKey(event));
        this.getRootElement().addEventListener('wheel', event => this.releaseNumberInputOnWheel(event), {capture: true});
        this.getRootElement().addEventListener('paste', event => this.normalizeIntegerInputSoon(event.target));
        this.getRootElement().addEventListener('input', event => {
            this.normalizeIntegerInput(event.target, false);
            this.syncDepreciationStartDefaultsFromEvent(event.target);
            this.syncSpecial7bDefaultsFromEvent(event.target, false);
            this.updateParkingDepreciationControlsFromEvent(event.target);
            this.updateTaxModeState();
            this.saveDraft();
            this.scheduleCalculate();
        });
        this.getRootElement().addEventListener('change', event => {
            this.normalizeIntegerInput(event.target, true);
            this.syncDepreciationStartDefaultsFromEvent(event.target);
            this.syncSpecial7bDefaultsFromEvent(event.target, true);
            this.updateParkingDepreciationControlsFromEvent(event.target);
            this.updateTaxModeState();
            this.saveDraft();
            this.calculate();
        });
    }

    initWorkspaceKey()
    {
        const params = new URLSearchParams(window.location.search);
        const urlKey = params.get('ws');
        const storedKey = localStorage.getItem(this.workspaceStorageKey);
        const key = this.isWorkspaceKey(urlKey) ? urlKey : (this.isWorkspaceKey(storedKey) ? storedKey : this.createWorkspaceKey());
        localStorage.setItem(this.workspaceStorageKey, key);
        if(urlKey !== key) {
            params.set('ws', key);
            const query = params.toString();
            window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
        }
        return key;
    }

    createWorkspaceKey()
    {
        const bytes = new Uint8Array(32);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    isWorkspaceKey(value)
    {
        return typeof value === 'string' && /^[a-zA-Z0-9_-]{32,80}$/.test(value);
    }

    releaseNumberInputOnWheel(event)
    {
        const active = document.activeElement;
        if(active?.matches?.('input[type="number"]') && this.getRootElement().contains(active)) {
            active.blur();
        }
    }

    prepareStaticInputs()
    {
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => {
            input.autocomplete = 'off';
            input.name = input.dataset.path.replaceAll('.', '_');
            if(input.dataset.integer !== undefined) {
                input.step = '1';
                input.inputMode = 'numeric';
            }
        });
    }

    initSectionToggles()
    {
        const state = this.loadSectionState();
        this.getRootElement().querySelectorAll('.rei-section').forEach((section, index) => {
            section.dataset.sectionId = section.dataset.sectionId || `section-${index}`;
            const collapsed = Boolean(state[section.dataset.sectionId]);
            this.setSectionCollapsed(section, collapsed, false);
            const button = section.querySelector('[data-role="section-toggle"]');
            if(button) {
                button.addEventListener('click', () => {
                    this.setSectionCollapsed(section, !section.classList.contains('is-collapsed'), true);
                });
            }
        });
    }

    setSectionCollapsed(section, collapsed, persist)
    {
        const body = section.querySelector('.rei-section-body');
        const button = section.querySelector('[data-role="section-toggle"]');
        const title = section.querySelector('h2')?.textContent || 'Sektion';
        section.classList.toggle('is-collapsed', collapsed);
        if(body) {
            body.hidden = collapsed;
        }
        if(button) {
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            button.setAttribute('aria-label', `${title} ${collapsed ? 'ausklappen' : 'einklappen'}`);
        }
        if(persist) {
            this.saveSectionState();
        }
    }

    setComparisonCollapsed(collapsed)
    {
        this.comparisonSection.classList.toggle('is-collapsed', collapsed);
        this.comparisonBody.hidden = collapsed;
        this.comparisonToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        this.comparisonToggle.setAttribute('aria-label', `Szenariovergleich ${collapsed ? 'ausklappen' : 'einklappen'}`);
        if(!collapsed) {
            window.setTimeout(() => this.comparisonTable.redraw(true), 0);
        }
    }

    saveSectionState()
    {
        const state = {};
        this.getRootElement().querySelectorAll('.rei-section').forEach(section => {
            state[section.dataset.sectionId] = section.classList.contains('is-collapsed');
        });
        try {
            localStorage.setItem(this.sectionStateKey, JSON.stringify(state));
        } catch(error) {
            // Ignore storage quota/private-mode failures; the sections still work for this page view.
        }
    }

    loadSectionState()
    {
        try {
            const json = localStorage.getItem(this.sectionStateKey);
            return json ? JSON.parse(json) : {};
        } catch(error) {
            localStorage.removeItem(this.sectionStateKey);
            return {};
        }
    }

    expandAllSections()
    {
        this.getRootElement().querySelectorAll('.rei-section').forEach(section => this.setSectionCollapsed(section, false, false));
        localStorage.removeItem(this.sectionStateKey);
    }

    addDefaultRows()
    {
        this.addParkingRow({label: 'Stellplatz', purchasePrice: 20000, monthlyRent: 80, buildingSharePercent: 80, landSharePercent: 20, depreciable: true, includedInPurchasePrice: true});
        this.addLoanRow({name: 'Bankdarlehen', principal: 310000, interestRatePercent: 4, initialRepaymentPercent: 2, startYear: 2026, startMonth: 1, fixedInterestYears: 10, constantAnnuity: true, redeemOnSale: true});
    }

    resetStaticInputs()
    {
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => {
            if(input.type === 'checkbox') {
                delete input.dataset.autoValue;
                input.checked = input.defaultChecked;
                return;
            }
            if(input.tagName.toLowerCase() === 'select') {
                delete input.dataset.autoValue;
                const selected = Array.from(input.options).find(option => option.defaultSelected);
                input.value = selected ? selected.value : input.options[0]?.value || '';
                return;
            }
            delete input.dataset.autoValue;
            input.value = input.defaultValue;
        });
    }

    addParkingRow(data = {})
    {
        const defaultStartYear = Number(this.value('property.completionYear') || this.value('property.purchaseYear') || 0);
        const defaultStartMonth = Number(this.value('property.completionMonth') || this.value('property.purchaseMonth') || 1);
        const row = this.row('parking', [
            ['label', 'Bezeichnung', 'text', data.label || 'Stellplatz'],
            ['purchasePrice', 'Kaufpreis', 'number', data.purchasePrice || 0],
            ['monthlyRent', 'Miete mtl.', 'number', data.monthlyRent || 0],
            ['buildingSharePercent', 'Gebäude %', 'number', data.buildingSharePercent ?? 80],
            ['landSharePercent', 'Grund %', 'number', data.landSharePercent ?? 20],
            ['depreciable', 'AfA', 'checkbox', data.depreciable ?? true],
            ['depreciationMode', 'AfA-Typ', 'segment', data.depreciationMode || 'building', [
                ['building', 'Tiefgarage wie Objekt'],
                ['custom', 'Außenstellplatz eigener Satz'],
            ]],
            ['depreciationRatePercent', 'AfA %', 'number', data.depreciationRatePercent ?? 5.26],
            ['depreciationStartYear', 'AfA Startjahr', 'number', data.depreciationStartYear || defaultStartYear],
            ['depreciationStartMonth', 'AfA Startmonat', 'number', data.depreciationStartMonth || defaultStartMonth],
            ['includedInPurchasePrice', 'im Kaufpreis', 'checkbox', data.includedInPurchasePrice ?? true],
        ]);
        this.parkingList.append(row);
        this.updateRepeaterNames(this.parkingList, 'parkingUnits');
        this.updateParkingDepreciationRateState(row);
        this.syncSpecial7bCalculatedCosts();
        this.saveDraft();
    }

    addConstructionInterestRow(data = {})
    {
        const row = this.row('constructionInterest', [
            ['label', 'Bezeichnung', 'text', data.label || 'Bauzeitzinsen'],
            ['year', 'Jahr', 'number', data.year || Number(this.value('property.purchaseYear') || 2026)],
            ['amount', 'Betrag', 'number', data.amount || 0],
            ['deductible', 'Werbungskosten', 'checkbox', data.deductible ?? true],
            ['financed', 'finanziert', 'checkbox', data.financed ?? false],
        ]);
        this.constructionInterestList.append(row);
        this.updateRepeaterNames(this.constructionInterestList, 'constructionInterest_yearlyEntries');
        this.saveDraft();
    }

    addLoanRow(data = {})
    {
        const row = this.row('loan', [
            ['name', 'Name', 'text', data.name || 'Darlehen'],
            ['principal', 'Kredithöhe', 'number', data.principal || 0],
            ['interestRatePercent', 'Zins %', 'number', data.interestRatePercent ?? 4],
            ['initialRepaymentPercent', 'Tilgung zu Beginn %', 'number', data.initialRepaymentPercent ?? 2],
            ['startYear', 'Startjahr', 'number', data.startYear || 2026],
            ['startMonth', 'Startmonat', 'number', data.startMonth || 1],
            ['interestOnlyYears', 'tilgungsfreie Jahre', 'number', data.interestOnlyYears || 0],
            ['repaymentRateAfterInterestOnlyPercent', 'Tilgung nach tilgungsfrei %', 'number', data.repaymentRateAfterInterestOnlyPercent ?? data.initialRepaymentPercent ?? 2],
            ['fixedInterestYears', 'Zinsbindung', 'number', data.fixedInterestYears || 10],
            ['followUpInterestRatePercent', 'Anschlusszins %', 'number', data.followUpInterestRatePercent ?? data.interestRatePercent ?? 4],
            ['specialRepaymentAmount', 'Sondertilgung', 'number', data.specialRepaymentAmount || 0],
            ['specialRepaymentYear', 'Sondertilg. Jahr', 'number', data.specialRepaymentYear || 0],
            ['grantAmount', 'Tilgungszuschuss', 'number', data.grantAmount || 0],
            ['grantYear', 'Zuschuss Jahr', 'number', data.grantYear || 0],
            ['constantAnnuity', 'Annuität konstant', 'checkbox', data.constantAnnuity ?? true],
            ['redeemOnSale', 'Ablösung Verkauf', 'checkbox', data.redeemOnSale ?? true],
        ]);
        this.loanList.append(row);
        this.updateRepeaterNames(this.loanList, 'loans');
        this.saveDraft();
    }

    row(type, fields)
    {
        const row = document.createElement('div');
        row.className = 'rei-repeater-row';
        row.dataset.type = type;
        fields.forEach(([field, label, inputType, value, options]) => {
            const wrapper = document.createElement(inputType === 'segment' ? 'div' : 'label');
            wrapper.textContent = label;
            if(inputType === 'checkbox') {
                wrapper.className = 'rei-check-field';
            }
            if(inputType === 'segment') {
                wrapper.className = 'rei-repeater-field';
                const group = document.createElement('div');
                group.className = 'rei-segment';
                (options || []).forEach(([optionValue, optionLabel]) => {
                    const optionWrapper = document.createElement('label');
                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.dataset.field = field;
                    input.value = optionValue;
                    input.checked = value === optionValue;
                    if(type === 'parking' && field === 'depreciationMode') {
                        input.addEventListener('change', () => requestAnimationFrame(() => this.updateParkingDepreciationRateState(row)));
                    }
                    const text = document.createElement('span');
                    text.textContent = optionLabel;
                    optionWrapper.append(input, text);
                    group.append(optionWrapper);
                });
                wrapper.append(group);
                row.append(wrapper);
                return;
            }

            const input = document.createElement('input');
            input.className = inputType === 'checkbox' ? 'form-check-input' : 'form-control form-control-sm';
            input.type = inputType;
            input.dataset.field = field;
            input.autocomplete = 'off';
            if(inputType === 'number') {
                if(this.isIntegerRepeaterField(type, field)) {
                    input.step = '1';
                    input.inputMode = 'numeric';
                    input.dataset.integer = '';
                    if(this.isMonthField(field)) {
                        input.min = '1';
                        input.max = '12';
                        input.dataset.month = '';
                    }
                } else {
                    input.step = '0.01';
                }
            }
            if(inputType === 'checkbox') {
                input.checked = Boolean(value);
            } else {
                input.value = value;
            }
            wrapper.append(input);
            row.append(wrapper);
        });
        const remove = document.createElement('button');
        remove.className = 'btn btn-sm btn-outline-danger';
        remove.type = 'button';
        remove.textContent = 'Entfernen';
        remove.addEventListener('click', () => {
            const container = row.parentElement;
            row.remove();
            if(container === this.parkingList) {
                this.updateRepeaterNames(this.parkingList, 'parkingUnits');
                this.syncSpecial7bCalculatedCosts();
            }
            if(container === this.constructionInterestList) {
                this.updateRepeaterNames(this.constructionInterestList, 'constructionInterest_yearlyEntries');
            }
            if(container === this.loanList) {
                this.updateRepeaterNames(this.loanList, 'loans');
            }
            this.saveDraft();
            this.calculate();
        });
        row.append(remove);
        return row;
    }

    updateRepeaterNames(container, prefix)
    {
        Array.from(container.children).forEach((row, index) => {
            row.querySelectorAll('[data-field]').forEach(input => {
                input.name = `${prefix}_${index}_${input.dataset.field}`;
            });
        });
    }

    updateParkingDepreciationControlsFromEvent(target)
    {
        const row = target?.closest?.('.rei-repeater-row[data-type="parking"]');
        if(row) {
            this.updateParkingDepreciationRateState(row);
        }
    }

    updateParkingDepreciationRateState(row)
    {
        const mode = row.querySelector('input[type="radio"][data-field="depreciationMode"]:checked')?.value || 'building';
        const depreciable = row.querySelector('[data-field="depreciable"]')?.checked ?? true;
        const rate = row.querySelector('[data-field="depreciationRatePercent"]');
        if(rate) {
            rate.disabled = !depreciable || mode !== 'custom';
        }
    }

    isIntegerRepeaterField(type, field)
    {
        if(type === 'constructionInterest') {
            return field === 'year';
        }
        if(type === 'loan') {
            return ['startYear', 'startMonth', 'interestOnlyYears', 'fixedInterestYears', 'specialRepaymentYear', 'specialRepaymentMonth', 'grantYear', 'grantMonth'].includes(field);
        }
        if(type === 'parking') {
            return ['depreciationStartYear', 'depreciationStartMonth'].includes(field);
        }
        return false;
    }

    isMonthField(field)
    {
        return field.toLowerCase().includes('month');
    }

    preventInvalidIntegerKey(event)
    {
        if(!event.target?.matches?.('[data-integer]')) {
            return;
        }
        if([',', '.', 'e', 'E', '+', '-'].includes(event.key)) {
            event.preventDefault();
        }
    }

    normalizeIntegerInputSoon(input)
    {
        if(!input?.matches?.('[data-integer]')) {
            return;
        }
        window.setTimeout(() => this.normalizeIntegerInput(input, true), 0);
    }

    normalizeIntegerInput(input, clamp)
    {
        if(!input?.matches?.('[data-integer]') || input.value === '') {
            return;
        }
        const value = String(input.value).replace(',', '.');
        const match = value.match(/^\d+/);
        if(!match) {
            input.value = '';
            return;
        }
        let number = Number.parseInt(match[0], 10);
        if(clamp && input.dataset.month !== undefined) {
            number = Math.min(Math.max(number, 1), 12);
        }
        input.value = String(number);
    }

    scheduleCalculate()
    {
        window.clearTimeout(this.calculateTimer);
        this.calculateTimer = window.setTimeout(() => this.calculate(), 250);
    }

    syncDepreciationStartDefaultsFromEvent(input)
    {
        if(!input?.matches?.('[data-path]')) {
            return;
        }
        if(['depreciation.startYear', 'depreciation.startMonth'].includes(input.dataset.path)) {
            input.dataset.autoValue = 'false';
            return;
        }
        if(['property.completionYear', 'property.completionMonth'].includes(input.dataset.path)) {
            this.syncDepreciationStartDefaults(false);
        }
    }

    syncDepreciationStartDefaults(force)
    {
        this.setAutoNumberDefault('depreciation.startYear', Number(this.value('property.completionYear') || 0), force);
        this.setAutoNumberDefault('depreciation.startMonth', Number(this.value('property.completionMonth') || 0), force);
    }

    setAutoNumberDefault(path, value, force)
    {
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        if(!input || value <= 0) {
            return;
        }
        if(force || input.value === '' || Number(input.value || 0) <= 0 || input.dataset.autoValue === 'true') {
            input.value = String(value);
            input.dataset.autoValue = 'true';
        }
    }

    syncSpecial7bDefaultsFromEvent(input, forceRule)
    {
        if(!input?.matches?.('[data-path]')) {
            if(input?.closest?.('[data-role="parking-list"]')) {
                this.syncSpecial7bCalculatedCosts();
            }
            return;
        }
        if(input.dataset.path === 'property.livingArea') {
            this.syncSpecial7bArea(false);
        }
        if(['property.purchaseYear', 'property.purchaseMonth'].includes(input.dataset.path)) {
            this.syncSpecial7bApplicationDate(false);
        }
        if(input.dataset.path === 'depreciation.special7bApplicationDate') {
            input.dataset.autoValue = 'false';
            this.syncSpecial7bRuleDefaults(forceRule);
        }
        if(this.special7bCostTriggerPaths.includes(input.dataset.path)) {
            this.syncSpecial7bCalculatedCosts();
        }
    }

    syncSpecial7bDefaults(forceRule)
    {
        this.syncSpecial7bArea(false);
        this.syncSpecial7bApplicationDate(false);
        this.syncSpecial7bRuleDefaults(forceRule);
        this.syncSpecial7bCalculatedCosts();
    }

    syncSpecial7bArea(force)
    {
        const areaInput = this.getRootElement().querySelector('[data-path="depreciation.special7bArea"]');
        const livingArea = Number(this.value('property.livingArea') || 0);
        if(!areaInput || livingArea <= 0) {
            return;
        }
        if(force || areaInput.value === '' || Number(areaInput.value || 0) <= 0) {
            areaInput.value = String(livingArea);
        }
    }

    syncSpecial7bRuleDefaults(force)
    {
        const date = String(this.value('depreciation.special7bApplicationDate') || '2023-01-01');
        const rule = this.special7bRules.find(item => date >= item.from && date < item.until) || this.special7bRules[1];
        this.setRuleDefault('depreciation.special7bConstructionCostLimitPerSqm', rule.constructionCostLimitPerSqm, force);
        this.setRuleDefault('depreciation.special7bLimitPerSqm', rule.specialAfaLimitPerSqm, force);
        this.setRuleDefault('depreciation.special7bRatePercent', rule.ratePercent, force);
        this.setRuleDefault('depreciation.special7bYears', rule.years, force);
    }

    syncSpecial7bApplicationDate(force)
    {
        const input = this.getRootElement().querySelector('[data-path="depreciation.special7bApplicationDate"]');
        const year = Number(this.value('property.purchaseYear') || 0);
        const month = Number(this.value('property.purchaseMonth') || 1);
        if(!input || year <= 0) {
            return;
        }
        if(force || input.value === '' || input.dataset.autoValue === 'true') {
            input.value = `${year}-${String(Math.min(Math.max(month, 1), 12)).padStart(2, '0')}-01`;
            input.dataset.autoValue = 'true';
        }
    }

    syncSpecial7bCalculatedCosts()
    {
        const basisInput = this.getRootElement().querySelector('[data-path="depreciation.special7bBasis"]');
        const costsPerSqmInput = this.getRootElement().querySelector('[data-role="special7b-costs-per-sqm"]');
        if(!basisInput) {
            return;
        }

        const basis = this.calculatedSpecial7bAssessmentBasis();
        const area = Number(this.value('depreciation.special7bArea') || this.value('property.livingArea') || 0);
        basisInput.value = this.numberValue(basis);
        if(costsPerSqmInput) {
            costsPerSqmInput.value = area > 0 ? this.numberValue(basis / area) : '0';
        }
    }

    calculatedSpecial7bAssessmentBasis()
    {
        const area = Number(this.value('depreciation.special7bArea') || this.value('property.livingArea') || 0);
        const limitPerSqm = Number(this.value('depreciation.special7bLimitPerSqm') || 0);
        const cap = area > 0 && limitPerSqm > 0 ? area * limitPerSqm : 0;
        const buildingBasis = this.calculatedBuildingDepreciationBasis();
        return cap > 0 ? Math.min(buildingBasis, cap) : buildingBasis;
    }

    calculatedBuildingDepreciationBasis()
    {
        const manualBuildingBasis = Number(this.value('depreciation.buildingBasis') || 0);
        if(manualBuildingBasis > 0) {
            return manualBuildingBasis;
        }

        const apartment = Number(this.value('property.apartmentPurchasePrice') || 0);
        const other = Number(this.value('property.otherPurchasePrice') || 0);
        const landShare = Number(this.value('property.landSharePercent') || 0) / 100;
        const buildingShare = Number(this.value('property.buildingSharePercent') || 0) / 100;
        let landCosts = (apartment + other) * landShare;
        let buildingCosts = (apartment + other) * buildingShare;
        let parkingTotal = 0;

        this.collectRows(this.parkingList).forEach(unit => {
            if(unit.includedInPurchasePrice) {
                const price = Number(unit.purchasePrice || 0);
                parkingTotal += price;
            }
        });

        const realEstatePurchasePrice = Math.max(apartment + other + parkingTotal, 0);
        const notaryRate = this.value('acquisitionCosts.notaryLandRegisterTaxTreatment') === 'immediate_deductible'
            ? 0
            : Number(this.value('acquisitionCosts.notaryPercent') || 0) + Number(this.value('acquisitionCosts.landRegisterPurchasePercent') || 0);
        const acquisitionCosts = realEstatePurchasePrice * (
            Number(this.value('acquisitionCosts.realEstateTransferTaxPercent') || 0)
            + notaryRate
            + Number(this.value('acquisitionCosts.brokerPercent') || 0)
        ) / 100 + Number(this.value('acquisitionCosts.otherAcquisitionCosts') || 0);
        const realEstateBase = landCosts + buildingCosts;
        const buildingRatio = realEstateBase > 0 ? buildingCosts / realEstateBase : 0;
        const propertyShare = realEstatePurchasePrice > 0 ? (apartment + other) / realEstatePurchasePrice : 0;

        return buildingCosts + acquisitionCosts * propertyShare * buildingRatio;
    }

    setRuleDefault(path, value, force)
    {
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        if(input && (force || input.value === '' || Number(input.value || 0) <= 0)) {
            input.value = String(value);
        }
    }

    async calculate()
    {
        window.clearTimeout(this.calculateTimer);
        const scenario = this.collectScenario();
        const sequence = ++this.calculateSequence;
        try {
            const result = await this.request('calculateScenario', {scenario}, {method: 'POST'});
            if(sequence === this.calculateSequence) {
                this.render(result);
            }
        } catch(error) {
            this.warnings.innerHTML = `<div class="alert alert-danger">${this.escape(error.message || error)}</div>`;
        }
    }

    async saveScenario()
    {
        const scenario = this.collectScenario();
        this.saveDraft();
        const response = await this.request('saveScenario', {workspaceKey: this.workspaceKey, name: scenario.scenarioName, scenario}, {method: 'POST'});
        await this.refreshScenarioList(response.id);
        this.warnings.innerHTML = '<div class="alert alert-success">Szenario gespeichert.</div>';
    }

    async renameScenario()
    {
        const id = this.scenarioList.value;
        if(!id) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Bitte zuerst ein gespeichertes Szenario auswählen.</div>';
            return;
        }

        const currentName = this.value('scenarioName') || this.scenarioRows.find(row => row.id === id)?.name || '';
        const newName = window.prompt('Neuer Szenarioname:', currentName);
        if(newName === null) {
            return;
        }
        if(newName.trim() === '') {
            this.warnings.innerHTML = '<div class="alert alert-warning">Der neue Szenarioname darf nicht leer sein.</div>';
            return;
        }

        const response = await this.request('renameScenario', {workspaceKey: this.workspaceKey, id, newName: newName.trim()}, {method: 'POST'});
        if(!response.id) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Szenario konnte nicht umbenannt werden.</div>';
            return;
        }

        const nameInput = this.getRootElement().querySelector('[data-path="scenarioName"]');
        if(nameInput) {
            this.setInputValue(nameInput, response.name || newName);
        }
        this.saveDraft();
        await this.refreshScenarioList(response.id);
        this.warnings.innerHTML = `<div class="alert alert-success">Szenario in "${this.escape(response.name || newName)}" umbenannt.</div>`;
    }

    async deleteScenario()
    {
        const id = this.scenarioList.value;
        if(!id) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Bitte zuerst ein gespeichertes Szenario auswählen.</div>';
            return;
        }

        const row = this.scenarioRows.find(item => item.id === id);
        const name = row?.name || id;
        if(!window.confirm(`Szenario "${name}" wirklich löschen?`)) {
            return;
        }

        const response = await this.request('deleteScenario', {workspaceKey: this.workspaceKey, id}, {method: 'POST'});
        if(!response.deleted) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Szenario konnte nicht gelöscht werden.</div>';
            await this.refreshScenarioList();
            return;
        }

        this.scenarioList.value = '';
        await this.refreshScenarioList();
        this.warnings.innerHTML = '<div class="alert alert-success">Gespeichertes Szenario gelöscht. Die aktuellen Eingaben bleiben als ungespeicherter Entwurf erhalten.</div>';
    }

    async refreshScenarioList(selectedId = '')
    {
        const response = await this.request('listScenarios', {workspaceKey: this.workspaceKey}, {method: 'POST'});
        this.scenarioRows = response.data || [];
        this.renderScenarioGroups();
        const selectedRow = selectedId ? this.scenarioRows.find(row => row.id === selectedId) : null;
        if(selectedRow) {
            this.scenarioGroupList.value = selectedRow.group || 'Ohne Gruppe';
        }
        this.renderScenarioOptions(selectedId || this.scenarioList.value);
    }

    renderScenarioGroups()
    {
        const currentGroup = this.scenarioGroupList.value;
        const groups = Array.from(new Set(this.scenarioRows.map(row => row.group || 'Ohne Gruppe'))).sort((a, b) => a.localeCompare(b, 'de'));
        this.scenarioGroupList.innerHTML = '<option value="">Alle Gruppen</option>';
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group;
            option.textContent = group;
            this.scenarioGroupList.append(option);
        });
        if(currentGroup && groups.includes(currentGroup)) {
            this.scenarioGroupList.value = currentGroup;
        }
    }

    renderScenarioOptions(selectedId = '')
    {
        const group = this.scenarioGroupList.value;
        const rows = group ? this.scenarioRows.filter(row => (row.group || 'Ohne Gruppe') === group) : this.scenarioRows;
        this.scenarioList.innerHTML = '<option value="">Gespeichertes Szenario</option>';
        rows.forEach(row => {
            const option = document.createElement('option');
            option.value = row.id;
            const groupLabel = group ? '' : `${row.group || 'Ohne Gruppe'} / `;
            option.textContent = row.updatedAt ? `${groupLabel}${row.name} (${this.formatTimestamp(row.updatedAt)})` : `${groupLabel}${row.name}`;
            this.scenarioList.append(option);
        });
        if(selectedId && rows.some(row => row.id === selectedId)) {
            this.scenarioList.value = selectedId;
        }
    }

    async loadSelectedScenario()
    {
        const id = this.scenarioList.value;
        if(!id) {
            return;
        }
        const response = await this.request('loadScenario', {workspaceKey: this.workspaceKey, id}, {method: 'POST'});
        if(!response.scenario) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Szenario konnte nicht geladen werden.</div>';
            return;
        }
        this.applyScenario(response.scenario);
        this.calculate();
        this.warnings.innerHTML = `<div class="alert alert-success">Szenario "${this.escape(response.name || id)}" geladen.</div>`;
    }

    async copyWorkspaceLink()
    {
        await this.copyText(this.urlWithParams({ws: this.workspaceKey, import: null}));
        this.warnings.innerHTML = '<div class="alert alert-success">Workspace-Link kopiert.</div>';
    }

    async shareScenario()
    {
        const scenario = this.collectScenario();
        const response = await this.request('shareScenario', {name: scenario.scenarioName, scenario}, {method: 'POST'});
        if(!response.token) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Szenario-Link konnte nicht erzeugt werden.</div>';
            return;
        }
        await this.copyText(this.urlWithParams({import: response.token, ws: null}));
        this.warnings.innerHTML = '<div class="alert alert-success">Import-Link für eine Kopie wurde kopiert.</div>';
    }

    async importSharedScenarioFromUrl()
    {
        const token = new URLSearchParams(window.location.search).get('import');
        if(!/^[a-f0-9]{48}$/.test(token || '')) {
            return;
        }
        const response = await this.request('importSharedScenario', {workspaceKey: this.workspaceKey, token}, {method: 'POST'});
        if(!response.scenario) {
            this.warnings.innerHTML = '<div class="alert alert-warning">Import-Link konnte nicht geladen werden.</div>';
            return;
        }
        this.applyScenario(response.scenario);
        await this.refreshScenarioList(response.id);
        this.calculate();
        window.history.replaceState({}, '', this.urlWithParams({ws: this.workspaceKey, import: null}));
        this.warnings.innerHTML = `<div class="alert alert-success">Szenario "${this.escape(response.name || response.id)}" als Kopie importiert.</div>`;
    }

    async copyText(text)
    {
        if(navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
            return;
        }
        window.prompt('Link kopieren:', text);
    }

    urlWithParams(changes)
    {
        const url = new URL(window.location.href);
        Object.entries(changes).forEach(([key, value]) => {
            if(value === null || value === undefined) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        });
        return url.toString();
    }

    async compareScenarios()
    {
        const base = this.collectScenario();
        const conservative = this.clone(base);
        conservative.scenarioName = 'konservativ';
        conservative.sale.annualValueIncreasePercent = Math.max(Number(base.sale.annualValueIncreasePercent || 0) - 1, 0);
        conservative.rent.annualIncreasePercent = Math.max(Number(base.rent.annualIncreasePercent || 0) - 1, 0);
        conservative.rent.vacancyPercent = Number(base.rent.vacancyPercent || 0) + 3;
        conservative.expenses.annualIncreasePercent = Number(base.expenses.annualIncreasePercent || 0) + 1;

        const optimistic = this.clone(base);
        optimistic.scenarioName = 'optimistisch';
        optimistic.sale.annualValueIncreasePercent = Number(base.sale.annualValueIncreasePercent || 0) + 1;
        optimistic.rent.annualIncreasePercent = Number(base.rent.annualIncreasePercent || 0) + 1;
        optimistic.rent.vacancyPercent = Math.max(Number(base.rent.vacancyPercent || 0) - 1, 0);

        const response = await this.request('compareScenarios', {scenarios: [base, conservative, optimistic]}, {method: 'POST'});
        this.setComparisonCollapsed(false);
        await this.comparisonTable.setData(response.data);
        this.comparisonTable.redraw(true);
    }

    collectScenario()
    {
        this.syncSpecial7bCalculatedCosts();
        const scenario = {};
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => this.setPath(scenario, input.dataset.path, this.inputValue(input)));
        scenario.parkingUnits = this.collectRows(this.parkingList);
        scenario.constructionInterest ??= {};
        scenario.constructionInterest.yearlyEntries = this.collectRows(this.constructionInterestList);
        scenario.loans = this.collectRows(this.loanList).map((loan, index) => ({...loan, priority: index + 1}));
        return scenario;
    }

    applyScenario(scenario)
    {
        this.restoringDraft = true;
        this.resetStaticInputs();
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => {
            const value = this.getPath(scenario, input.dataset.path);
            if(value !== undefined) {
                this.setInputValue(input, value);
            }
        });

        this.parkingList.innerHTML = '';
        (scenario.parkingUnits || []).forEach(row => this.addParkingRow(row));
        if(!this.parkingList.children.length) {
            this.addParkingRow({label: 'Stellplatz', buildingSharePercent: 80, landSharePercent: 20, depreciable: true, includedInPurchasePrice: true});
        }

        this.constructionInterestList.innerHTML = '';
        (scenario.constructionInterest?.yearlyEntries || []).forEach(row => this.addConstructionInterestRow(row));

        this.loanList.innerHTML = '';
        (scenario.loans || []).forEach(row => this.addLoanRow(row));
        if(this.getPath(scenario, 'depreciation.startYear') === undefined && this.getPath(scenario, 'depreciation.startMonth') === undefined) {
            this.syncDepreciationStartDefaults(true);
        }
        this.syncSpecial7bDefaults(false);
        this.normalizeIntegerInputs();
        this.updateTaxModeState();
        this.restoringDraft = false;
        this.saveDraft();
    }

    newScenario()
    {
        if(!window.confirm('Aktuelles Szenario verwerfen und eine neue Immobilie erfassen?')) {
            return;
        }
        localStorage.removeItem(this.draftKey);
        this.expandAllSections();
        this.resetForNewScenario();
        this.saveDraft();
        this.calculate();
    }

    resetForNewScenario()
    {
        const defaults = this.newScenarioDefaults();
        this.restoringDraft = true;
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => {
            const value = defaults[input.dataset.path];
            if(value !== undefined) {
                this.setInputValue(input, value);
                return;
            }
            if(input.type === 'checkbox') {
                input.checked = false;
                return;
            }
            if(input.tagName.toLowerCase() === 'select') {
                input.value = input.options[0]?.value || '';
                return;
            }
            input.value = input.type === 'number' ? '0' : '';
        });
        this.parkingList.innerHTML = '';
        this.constructionInterestList.innerHTML = '';
        this.loanList.innerHTML = '';
        this.updateRepeaterNames(this.parkingList, 'parkingUnits');
        this.updateRepeaterNames(this.constructionInterestList, 'constructionInterest_yearlyEntries');
        this.updateRepeaterNames(this.loanList, 'loans');
        this.scenarioList.value = '';
        this.syncDepreciationStartDefaults(true);
        this.syncSpecial7bDefaults(false);
        this.normalizeIntegerInputs();
        this.updateTaxModeState();
        this.restoringDraft = false;
    }

    updateTaxModeState()
    {
        const calculationMethod = this.value('tax.calculationMethod');
        const marginalRateInput = this.getRootElement().querySelector('[data-role="marginal-tax-rate"]');
        if(!marginalRateInput) {
            return;
        }
        const section32a = calculationMethod === 'section_32a';
        marginalRateInput.disabled = section32a;
        marginalRateInput.title = section32a ? 'Im §32a-Modus wird die Steuer über den Tarif berechnet.' : '';
    }

    newScenarioDefaults()
    {
        return {
            scenarioName: 'Neues Szenario',
            scenarioGroup: '',
            'property.state': 'BY',
            'property.purchaseYear': 2026,
            'property.purchaseMonth': 1,
            'property.completionYear': 2026,
            'property.completionMonth': 1,
            'property.rentStartYear': 2026,
            'property.rentStartMonth': 1,
            'property.saleYear': 2036,
            'property.saleMonth': 12,
            'property.landSharePercent': 20,
            'property.buildingSharePercent': 80,
            'property.newBuilding': true,
            'acquisitionCosts.realEstateTransferTaxPercent': 3.5,
            'acquisitionCosts.notaryPercent': 1.5,
            'acquisitionCosts.landRegisterPurchasePercent': 0.5,
            'acquisitionCosts.landRegisterLienPercent': 0.5,
            'acquisitionCosts.financingCostsDeductible': true,
            'acquisitionCosts.notaryLandRegisterTaxTreatment': 'afa_basis',
            'depreciation.startYear': 2026,
            'depreciation.startMonth': 1,
            'depreciation.degressiveRatePercent': 5,
            'depreciation.linearRatePercent': 3,
            'depreciation.degressiveActive': true,
            'depreciation.autoSwitchToLinear': true,
            'depreciation.special7bConstructionCostLimitPerSqm': 5200,
            'depreciation.special7bLimitPerSqm': 4000,
            'depreciation.special7bRatePercent': 5,
            'depreciation.special7bYears': 4,
            'depreciation.furnitureRatePercent': 10,
            'tax.calculationMethod': 'marginal_rate',
            'tax.assessmentType': 'single',
            'tax.taxYear': 2026,
            'tax.churchTax': false,
            'tax.churchTaxState': 'BY',
            'tax.taxableIncomeAnnualIncreasePercent': 0,
            'tax.marginalTaxRatePercent': 42,
            'sale.sellingCostsPercent': 1,
            'sale.taxFreeSale': true,
            'settings.discountRatePercent': 5,
            'settings.initialEquityAmount': 0,
            'settings.autoSpecialRepaymentMode': 'none',
        };
    }

    normalizeIntegerInputs()
    {
        this.getRootElement().querySelectorAll('[data-integer]').forEach(input => this.normalizeIntegerInput(input, true));
    }

    getPath(source, path)
    {
        return path.split('.').reduce((cursor, part) => cursor && cursor[part] !== undefined ? cursor[part] : undefined, source);
    }

    setInputValue(input, value)
    {
        if(input.type === 'checkbox') {
            input.checked = Boolean(value);
            return;
        }
        input.value = value ?? '';
        this.normalizeIntegerInput(input, true);
    }

    saveDraft()
    {
        if(this.restoringDraft) {
            return;
        }
        try {
            localStorage.setItem(this.draftKey, JSON.stringify(this.collectScenario()));
        } catch(error) {
            // Ignore storage quota/private-mode failures; server-side calculation still works.
        }
    }

    loadDraft()
    {
        try {
            const json = localStorage.getItem(this.draftKey);
            return json ? JSON.parse(json) : null;
        } catch(error) {
            localStorage.removeItem(this.draftKey);
            return null;
        }
    }

    collectRows(container)
    {
        return Array.from(container.children).map(row => {
            const data = {};
            row.querySelectorAll('[data-field]').forEach(input => {
                const value = this.inputValue(input);
                if(value !== undefined) {
                    data[input.dataset.field] = value;
                }
            });
            return data;
        });
    }

    inputValue(input)
    {
        if(input.type === 'checkbox') {
            return input.checked;
        }
        if(input.type === 'radio') {
            return input.checked ? input.value : undefined;
        }
        if(input.type === 'number') {
            if(input.dataset.integer !== undefined) {
                return input.value === '' ? 0 : Number.parseInt(input.value, 10);
            }
            return input.value === '' ? 0 : Number(input.value);
        }
        return input.value;
    }

    setPath(target, path, value)
    {
        const parts = path.split('.');
        let cursor = target;
        parts.forEach((part, index) => {
            if(index === parts.length - 1) {
                cursor[part] = value;
                return;
            }
            cursor[part] ??= {};
            cursor = cursor[part];
        });
    }

    value(path)
    {
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        return input ? this.inputValue(input) : null;
    }

    render(result)
    {
        this.renderWarnings(result.warnings || []);
        this.renderSummary(result.summary || {}, result.scales || {});
        this.renderSidebarCostBreakdown(result.summary || {});
        this.renderSpecial7bInputs(result.summary?.special7b || {});
        this.renderCalculationBreakdown(result.calculationBreakdown || []);
        this.renderScaleLegend(result.scaleLegend || []);
        this.formulas.innerHTML = (result.formulas || []).map(formula => `<div>${this.escape(formula)}</div>`).join('');
        this.yearTable.setData(result.yearlyRows || []);
        this.rowCount.textContent = `${(result.yearlyRows || []).length} Jahre`;
    }

    renderWarnings(warnings)
    {
        this.warnings.innerHTML = warnings.map(item => `<div class="alert alert-${this.escape(item.level || 'info')}">${this.escape(item.message)}</div>`).join('');
    }

    renderSummary(summary, scales)
    {
        const cards = [
            {label: 'Kaufpreis', value: this.eur(summary.purchasePrice), calcKey: 'calc-gesamtkaufpreis'},
            {label: 'Gesamtkosten', value: this.eur(summary.totalCosts), calcKey: 'calc-gesamtkosten'},
            {label: 'Anfangsschuld', value: this.eur(summary.initialDebt)},
            {label: 'monatliche Miete', value: this.eur(summary.monthlyRent)},
            {label: 'Bruttorendite', value: this.pct(summary.grossYield)},
            {label: 'Nettorendite', value: this.pct(summary.netYield)},
            {label: 'CF vor Steuer', value: this.eur(summary.firstFullYearNetCashflowBeforeTax)},
            {label: 'CF nach Steuer', value: this.eur(summary.firstFullYearNetCashflowAfterTax)},
            {label: 'Netto-Vermögenseffekt', value: this.eur(summary.netWorthEffect), calcKey: 'calc-netto-vermoegenseffekt'},
            {label: 'Hebel-/Effizienz', value: this.pct(summary.leverageEfficiency), scale: scales.leverageEfficiency, calcKey: 'calc-hebel-effizienz'},
            {label: 'NPV', value: this.eur(summary.npv), calcKey: 'calc-npv'},
            {label: 'Barwert-Hebel', value: this.pct(summary.npvLeverageEfficiency), scale: scales.npvLeverageEfficiency, calcKey: 'calc-barwert-hebel'},
            {label: 'FV konservativ', value: this.eur(summary.futureValueConservative), calcKey: 'calc-fv-konservativ'},
            {label: 'FV-Hebel konservativ', value: this.pct(summary.fvLeverageConservative), scale: scales.fvLeverageConservative, calcKey: 'calc-fv-hebel-konservativ'},
            {label: 'FV liquiditätsorientiert', value: this.eur(summary.futureValueLiquidity), calcKey: 'calc-fv-liquiditaetsorientiert'},
            {label: 'FV-Hebel liquiditätsorientiert', value: this.pct(summary.fvLeverageLiquidity), scale: scales.fvLeverageLiquidity, calcKey: 'calc-fv-hebel-liquiditaetsorientiert'},
            {label: 'DSCR', value: this.dec(summary.dscr, 2), scale: scales.dscr, calcKey: 'calc-dscr'},
            {label: 'Debt Yield', value: this.pct(summary.debtYield), scale: scales.debtYield, calcKey: 'calc-debt-yield'},
        ];
        if(Number(summary.totalEquityInvested || 0) > 0) {
            cards.push(
                {label: 'Anfangs-Eigenkapital', value: this.eur(summary.initialEquityAmount), calcKey: 'calc-anfangs-eigenkapital'},
                {label: 'nicht finanzierte BZZ', value: this.eur(summary.constructionInterestCashTotal)},
                {label: 'Kapitalnachschüsse', value: this.eur(summary.equityCapitalCalls), calcKey: 'calc-kapitalnachschuesse'},
                {label: 'eingesetztes EK', value: this.eur(summary.totalEquityInvested), calcKey: 'calc-eingesetztes-eigenkapital'},
                {label: 'Netto-Endvermögen', value: this.eur(summary.equityDistributions), calcKey: 'calc-netto-endvermoegen'},
                {label: 'Vermögensgewinn nach EK', value: this.eur(summary.equityNetGain), calcKey: 'calc-netto-vermoegensgewinn-nach-ek'},
                {label: 'EK-Multiple', value: `${this.dec(summary.equityMultiple, 2)}x`, calcKey: 'calc-ek-multiple'},
                {label: 'EK-Rendite gesamt', value: this.pct(summary.equityTotalReturn), calcKey: 'calc-ek-rendite-gesamt'},
                {label: 'EK-Rendite p.a.', value: this.pct(summary.equityAnnualizedReturn), calcKey: 'calc-annualisierte-ek-rendite'},
            );
        }
        this.summary.innerHTML = cards.map(card => `
            <article class="rei-card">
                <span>${this.escape(card.label)}</span>
                ${card.calcKey ? `<button class="rei-card-link" type="button" data-calc-target="${this.escape(card.calcKey)}" title="Rechenweg anzeigen" aria-label="Rechenweg ${this.escape(card.label)} anzeigen"><i class="bi bi-bookmark" aria-hidden="true"></i></button>` : ''}
                <strong>${card.value || '0'}</strong>
                ${card.scale ? `<em class="badge text-bg-${this.escape(card.scale.variant)}">${this.escape(card.scale.label)}</em>` : ''}
            </article>
        `).join('');
        this.summary.querySelectorAll('[data-calc-target]').forEach(button => {
            button.addEventListener('click', () => this.scrollToCalculationBreakdown(button.dataset.calcTarget));
        });
    }

    renderSidebarCostBreakdown(summary)
    {
        const acquisition = summary.acquisitionBreakdown || {};
        const allocation = summary.costAllocation || {};
        this.renderPurchaseBreakdown(allocation, summary);
        this.renderAcquisitionBreakdown(acquisition);
        this.renderFinancingBreakdown(acquisition, summary);
        this.renderTotalBreakdown(summary);
        this.renderDepreciationBasisBreakdown(summary);
    }

    renderPurchaseBreakdown(values, summary)
    {
        const rows = [
            ['Wohnung', values.apartmentPurchasePrice],
            ['Stellplätze', values.parkingTotal],
            ['Küche/Möbel', values.furnitureCosts],
            ['sonstige Bestandteile', values.otherPurchasePrice],
            ['Zwischensumme Kaufpreis', summary.purchasePrice, true],
            ['davon Grundstückskosten', values.landCosts],
            ['davon Herstellungskosten/Gebäude', values.buildingCosts],
        ];
        this.purchaseBreakdown.innerHTML = this.renderBreakdownRows(rows);
    }

    renderAcquisitionBreakdown(values)
    {
        const rows = [
            ['Bemessungsgrundlage Immobilienkauf', values.realEstatePurchasePrice, true],
            ['Grunderwerbsteuer', values.realEstateTransferTax],
            ['Notar', values.notary],
            ['Grundbuch Kauf', values.landRegisterPurchase],
            ['Makler', values.broker],
            ['sonstige Erwerbsnebenkosten', values.otherAcquisitionCosts],
            ['Zwischensumme Erwerbsnebenkosten', values.acquisitionCostsWithoutFinancing, true],
            ['davon sofortige Erwerbs-WK', values.immediateDeductibleAcquisitionCosts],
            ['davon AfA-relevante Erwerbsnebenkosten', values.depreciationRelevantAcquisitionCostsWithoutFinancing],
        ];
        this.acquisitionBreakdown.innerHTML = this.renderBreakdownRows(rows);
    }

    renderFinancingBreakdown(values, summary)
    {
        const rows = [
            ['Pfandrecht', values.landRegisterLien],
            ['Bauzeitzinsen brutto', summary.constructionInterestTotal],
            ['davon nicht finanziert', summary.constructionInterestCashTotal],
            ['davon finanziert', summary.constructionInterestFinancedTotal],
            ['sonstige Kreditkosten', values.otherFinancingCosts],
            ['Zwischensumme einmalige Finanzierungskosten', summary.oneTimeFinancingCostsTotal, true],
        ];
        this.financingBreakdown.innerHTML = this.renderBreakdownRows(rows);
    }

    renderTotalBreakdown(summary)
    {
        const rows = [
            ['Zwischensumme Kaufpreis', summary.purchasePrice],
            ['Zwischensumme Erwerbsnebenkosten', summary.acquisitionCostsWithoutFinancing],
            ['Zwischensumme einmalige Finanzierungskosten', summary.oneTimeFinancingCostsTotal],
            ['Gesamtaufwand', summary.totalInvestmentCost, true],
        ];
        this.totalBreakdown.innerHTML = this.renderBreakdownRows(rows);
    }

    renderDepreciationBasisBreakdown(summary)
    {
        const special = summary.special7b || {};
        const parkingRateLabel = summary.parkingDepreciationMixedRates
            ? 'gemischt'
            : (Number(summary.parkingDepreciationRate || 0) > 0 ? this.pct(summary.parkingDepreciationRate) : '');
        const parkingBasisLabel = parkingRateLabel ? `Stellplatz AfA-Basis (${parkingRateLabel})` : 'Stellplatz AfA-Basis';
        const rows = [
            ['Gebäude-AfA-Basis', summary.buildingDepreciationBasis, true],
            ['Gebäude-AH-Kosten vor §7b-Kappung', special.eligibleCosts, false],
            ['Gebäude-AH-Kosten je m²', special.costsPerSqm, false, value => `${this.eur(value)} / m²`],
            ['§7b-Fläche', special.area, false, value => `${this.dec(value, 2)} m²`],
            ['§7b Baukostenobergrenze', special.constructionCostLimitPerSqm, false, value => `${this.eur(value)} / m²`],
            ['§7b Baukosten Ist', special.actualConstructionCostPerSqm, false, value => value > 0 ? `${this.eur(value)} / m²` : 'nicht erfasst'],
            ['§7b Baukostenobergrenze absolut', special.constructionCostCap, true],
            ['§7b Förderhöchstgrenze', special.limitPerSqm, false, value => `${this.eur(value)} / m²`],
            ['§7b rechnerische Obergrenze', special.cap, true],
            ['§7b begünstigte AH-Kosten/Bemessungsgrundlage', special.assessmentBasis, true],
            ['§7b Bemessungsgrundlage je m²', special.assessmentBasisPerSqm, false, value => `${this.eur(value)} / m²`],
            ['§7b Sonder-AfA p.a.', special.annualAmount, true],
            ['Möbel/Küche AfA-Basis', summary.furnitureDepreciationBasis, true],
            [parkingBasisLabel, summary.parkingDepreciationBasis, true],
        ];
        this.depreciationBasisBreakdown.innerHTML = this.renderBreakdownRows(rows);
    }

    renderBreakdownRows(rows)
    {
        return rows.map(([label, value, important, formatter]) => `
            <div class="rei-breakdown-row${important ? ' rei-breakdown-row--strong' : ''}">
                <span>${this.escape(label)}</span>
                <strong>${formatter ? formatter(value) : this.eur(value)}</strong>
            </div>
        `).join('');
    }

    renderSpecial7bInputs(special)
    {
        const basisInput = this.getRootElement().querySelector('[data-path="depreciation.special7bBasis"]');
        const costsPerSqmInput = this.getRootElement().querySelector('[data-role="special7b-costs-per-sqm"]');
        if(basisInput && special.assessmentBasis !== undefined) {
            basisInput.value = this.numberValue(special.assessmentBasis);
        }
        if(costsPerSqmInput && special.assessmentBasisPerSqm !== undefined) {
            costsPerSqmInput.value = this.numberValue(special.assessmentBasisPerSqm);
        }
    }

    renderCalculationBreakdown(rows)
    {
        this.calculationBreakdown.innerHTML = rows.map(row => `
            <article class="rei-calc-row" id="${this.escape(row.key || '')}">
                <div>
                    <span>${this.escape(row.group || '')}</span>
                    <strong>${this.escape(row.label || '')}${this.renderSourceLink(row.source)}</strong>
                    <p>${this.escape(row.description || '')}</p>
                    <code>${this.escape(row.formula || '')}</code>
                    <em>${this.escape(row.values || '')}</em>
                </div>
                <b>${this.formatBreakdownResult(row)}</b>
            </article>
        `).join('');
    }

    scrollToCalculationBreakdown(key)
    {
        const row = key ? document.getElementById(key) : null;
        if(!row) {
            return;
        }
        row.scrollIntoView({behavior: 'smooth', block: 'start'});
        row.classList.add('rei-calc-row--highlight');
        window.setTimeout(() => row.classList.remove('rei-calc-row--highlight'), 1500);
    }

    formatBreakdownResult(row)
    {
        if(row.format === 'percent') {
            return this.pct(row.result);
        }
        if(row.format === 'decimal') {
            return `${this.dec(row.result, 2)}x`;
        }
        return this.eur(row.result);
    }

    renderSourceLink(source)
    {
        if(!source?.url) {
            return '';
        }
        return ` <a class="rei-source-link" href="${this.escape(source.url)}" target="_blank" rel="noopener" title="${this.escape(source.label || 'Offizielle Quelle')}">↗</a>`;
    }

    renderScaleLegend(groups)
    {
        this.scaleLegend.innerHTML = groups.map(group => `
            <article class="rei-scale-group">
                <h3>${this.escape(group.title || '')}</h3>
                <p>${this.escape(group.description || '')}</p>
                <div>
                    ${(group.items || []).map(item => `
                        <div class="rei-scale-row">
                            <span>${this.escape(item.range || '')}</span>
                            <em class="badge text-bg-${this.escape(item.variant || 'secondary')}">${this.escape(item.label || '')}</em>
                            <small>${this.escape(item.description || '')}</small>
                        </div>
                    `).join('')}
                </div>
            </article>
        `).join('');
    }

    sum(values)
    {
        return values.reduce((total, value) => total + Number(value || 0), 0);
    }

    lastValue(rows, field)
    {
        if(!rows.length) {
            return 0;
        }
        return Number(rows[rows.length - 1][field] || 0);
    }

    clone(value)
    {
        return JSON.parse(JSON.stringify(value));
    }

    dec(value, digits = 2)
    {
        const number = Number(value || 0);
        return new Intl.NumberFormat('de-DE', {minimumFractionDigits: digits, maximumFractionDigits: digits}).format(number);
    }

    numberValue(value)
    {
        return String(Math.round(Number(value || 0) * 100) / 100);
    }

    formatTimestamp(value)
    {
        const date = new Date(value);
        if(Number.isNaN(date.getTime())) {
            return value;
        }
        return new Intl.DateTimeFormat('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    eur(value)
    {
        return `${this.dec(Math.round(Number(value || 0)), 0)} €`;
    }

    pct(value)
    {
        return `${this.dec(Number(value || 0) * 100, 2)} %`;
    }

    escape(value)
    {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char]));
    }
}

Weblication.registerClass(GUI_InvestmentCalculator);
