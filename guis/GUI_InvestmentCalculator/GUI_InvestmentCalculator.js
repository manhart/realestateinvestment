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
        this.controlId = 0;
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
            'depreciation.buildingBasisOverrideEnabled',
            'depreciation.special7bArea',
            'depreciation.special7bLimitPerSqm',
            'depreciation.special7bActualConstructionCostPerSqm',
        ];
        this.autoManagedPaths = new Set([
            'depreciation.startYear',
            'depreciation.startMonth',
            'depreciation.buildingBasis',
            'depreciation.special7bApplicationDate',
            'depreciation.special7bArea',
            'depreciation.special7bConstructionCostLimitPerSqm',
            'depreciation.special7bLimitPerSqm',
            'depreciation.special7bBasis',
            'depreciation.special7bRatePercent',
            'depreciation.special7bYears',
            'sale.parkingAnnualValueIncreasePercent',
        ]);
        this.fieldHelpTexts = this.createFieldHelpTexts();
        this.switchNotes = this.createSwitchNotes();
        this.lastResultSummary = {};
        this.form = this.element('[data-role="scenario-form"]');
        this.summary = this.element('[data-role="summary"]');
        this.purchaseBreakdown = this.element('[data-role="purchase-breakdown"]');
        this.acquisitionBreakdown = this.element('[data-role="acquisition-breakdown"]');
        this.financingBreakdown = this.element('[data-role="financing-breakdown"]');
        this.totalBreakdown = this.element('[data-role="total-breakdown"]');
        this.saleBreakdown = this.element('[data-role="sale-breakdown"]');
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
        this.enhanceFieldGuidance();
        this.initInfoPopovers();
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
        this.updateDependentFieldStates();
        this.updateBuildingBasisOverrideState();
        this.syncSpecial7bCalculatedCosts();
        this.syncParkingValueIncreaseDefault(false);
        this.updateGuidanceState();
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
        this.element('[data-role="add-parking"]').addEventListener('click', () => this.addParkingRow({label: 'Stellplatz', buildingSharePercent: 80, landSharePercent: 20, depreciationMode: 'building_basis', includedInPurchasePrice: true}));
        this.element('[data-role="add-construction-interest"]').addEventListener('click', () => this.addConstructionInterestRow({label: 'Bauzeitzinsen', year: Number(this.value('property.purchaseYear') || 2026), deductible: true}));
        this.element('[data-role="add-loan"]').addEventListener('click', () => this.addLoanRow({name: 'Darlehen', startYear: Number(this.value('property.purchaseYear') || 2026), startMonth: Number(this.value('property.purchaseMonth') || 1), fixedInterestYears: 10, constantAnnuity: true, redeemOnSale: true}));
        this.getRootElement().addEventListener('keydown', event => this.preventInvalidIntegerKey(event));
        this.getRootElement().addEventListener('wheel', event => this.releaseNumberInputOnWheel(event), {capture: true});
        this.getRootElement().addEventListener('paste', event => this.normalizeIntegerInputSoon(event.target));
        this.getRootElement().addEventListener('input', event => {
            this.normalizeIntegerInput(event.target, false);
            this.markAutoFieldManualFromEvent(event.target);
            this.syncDepreciationStartDefaultsFromEvent(event.target);
            this.syncBuildingBasisFromEvent(event.target);
            this.syncSpecial7bDefaultsFromEvent(event.target, false);
            this.syncParkingValueIncreaseDefaultFromEvent(event.target);
            this.updateParkingDepreciationControlsFromEvent(event.target);
            this.updateTaxModeState();
            this.updateDependentFieldStates();
            this.updateGuidanceState();
            this.saveDraft();
            this.scheduleCalculate();
        });
        this.getRootElement().addEventListener('change', event => {
            this.normalizeIntegerInput(event.target, true);
            this.markAutoFieldManualFromEvent(event.target);
            this.syncDepreciationStartDefaultsFromEvent(event.target);
            this.syncBuildingBasisFromEvent(event.target);
            this.syncSpecial7bDefaultsFromEvent(event.target, true);
            this.syncParkingValueIncreaseDefaultFromEvent(event.target);
            this.updateParkingDepreciationControlsFromEvent(event.target);
            this.updateTaxModeState();
            this.updateDependentFieldStates();
            this.updateGuidanceState();
            this.saveDraft();
            this.calculate();
        });
    }

    initInfoPopovers()
    {
        if(!window.bootstrap?.Popover) {
            return;
        }
        this.getRootElement().querySelectorAll('[data-bs-toggle="popover"]').forEach(element => this.initPopover(element));
    }

    initPopover(element)
    {
        if(!window.bootstrap?.Popover || element.dataset.popoverReady === 'true') {
            return;
        }
        new window.bootstrap.Popover(element);
        element.dataset.popoverReady = 'true';
    }

    createFieldHelpTexts()
    {
        return {
            'property.livingArea': 'Die Wohnfläche wird für Mietkennzahlen und die §7b-Obergrenze je Quadratmeter verwendet.',
            'property.apartmentPurchasePrice': 'Kaufpreis der Wohnung ohne separat erfasste Stellplätze und ohne Küche/Möbel.',
            'property.otherPurchasePrice': 'Weitere kaufpreisrelevante Bestandteile, die wie die Wohnung in Grund- und Gebäudeanteil aufgeteilt werden.',
            'property.furniturePurchasePrice': 'Küche oder Möbel werden separat behandelt, weil sie meist nicht in die Gebäude-AfA gehören.',
            'property.landSharePercent': 'Grund und Boden ist nicht abschreibbar. Dieser Anteil mindert die Gebäude-AfA-Basis.',
            'property.buildingSharePercent': 'Der Gebäudeanteil ist die Basis für die normale Gebäude-AfA, ergänzt um anteilige AfA-relevante Nebenkosten.',
            'rent.apartmentMonthlyRent': 'Monatliche Nettokaltmiete der Wohnung ohne Stellplätze und ohne umlagefähige Nebenkosten.',
            'rent.annualIncreasePercent': 'Annahme, wie stark die Miete im Zeitverlauf steigt. Sie beeinflusst Einnahmen, Cashflow und Verkaufskennzahlen indirekt.',
            'rent.increaseEveryYears': 'Legt fest, ob die Mietsteigerung jährlich oder nur alle x Jahre angewendet wird.',
            'rent.vacancyPercent': 'Sicherheitsabschlag für Leerstand oder Mietausfall. Er reduziert die angesetzte Jahresmiete.',
            'rent.otherAnnualIncome': 'Sonstige jährliche Einnahmen, die zusätzlich zur Miete angesetzt werden.',
            'expenses.nonRecoverableHausgeldMonthly': 'Nicht umlagefähige laufende Eigentümerkosten. Sie belasten Liquidität und sind in der Regel steuerlich relevant.',
            'expenses.maintenanceReserveMonthly': 'Rücklagen sind liquiditätswirksam. Steuerlich hängt die Behandlung vom konkreten Zahlungsfluss und Nachweis ab.',
            'expenses.managementMonthly': 'Verwalterkosten sind laufende Kosten und werden in der VuV-Rechnung als abzugsfähige laufende Kosten berücksichtigt.',
            'expenses.servicePoolMonthly': 'Sonstige laufende vermietungsbezogene Kosten, z. B. Servicepool oder Betreuung.',
            'expenses.furnitureReserveMonthly': 'Liquiditätsrücklage für Möbel/Küche. Das ist keine AfA, sondern ein laufender Cashflow-Ansatz.',
            'expenses.otherMonthlyCosts': 'Weitere laufende Kosten, die monatlich in Cashflow und Werbungskostenlogik einfließen.',
            'expenses.annualIncreasePercent': 'Annahme, wie stark laufende Kosten im Zeitverlauf steigen.',
            'acquisitionCosts.realEstateTransferTaxPercent': 'Grunderwerbsteuer auf den steuerpflichtigen Immobilienkaufpreis. Möbel/Küche sind separat zu prüfen.',
            'acquisitionCosts.notaryPercent': 'Notarkosten in Prozent vom Kaufpreis. Je nach steuerlicher Auswahl sofortige Werbungskosten oder AfA-relevante Nebenkosten.',
            'acquisitionCosts.landRegisterPurchasePercent': 'Grundbuchkosten für den Kauf. Die steuerliche Behandlung folgt der Auswahl Notar/Grundbuch steuerlich.',
            'acquisitionCosts.landRegisterLienPercent': 'Kosten für Eintragung des Grundpfandrechts, berechnet auf die Darlehenssumme.',
            'acquisitionCosts.brokerPercent': 'Maklerkosten in Prozent vom Kaufpreis. Im Modell werden sie als Erwerbsnebenkosten berücksichtigt.',
            'acquisitionCosts.otherAcquisitionCosts': 'Weitere einmalige Erwerbsnebenkosten, die nicht in den Prozentfeldern enthalten sind.',
            'acquisitionCosts.otherFinancingCosts': 'Einmalige Kreditkosten außerhalb des Pfandrechts, z. B. Bankgebühren.',
            'acquisitionCosts.notaryLandRegisterTaxTreatment': 'Steuert, ob Notar/Grundbuch sofort als Werbungskosten oder über die AfA-Basis berücksichtigt werden.',
            'acquisitionCosts.financingCostsDeductible': 'Aktiviert den steuerlichen Ansatz von Pfandrecht und sonstigen Kreditkosten als Werbungskosten.',
            'settings.initialEquityAmount': 'Vom Anleger zu Beginn eingebrachtes Eigenkapital. Es wird nicht automatisch aus Bauzeitzinsen oder negativen Cashflows erhöht.',
            'settings.autoSpecialRepaymentMode': 'Optional können positive echte Cashflows zur Sondertilgung genutzt werden. Opportunitätszinsen sind keine verfügbare Liquidität.',
            'settings.discountRatePercent': 'Vergleichsrendite für Barwert und Future Value. Sie ändert nicht den nominalen Netto-Vermögenseffekt.',
            'depreciation.startYear': 'Startjahr der normalen AfA. Bei Neubau wird es automatisch aus der Fertigstellung vorbelegt.',
            'depreciation.startMonth': 'Startmonat der normalen AfA. Die normale Gebäude-AfA wird monatsgenau gerechnet.',
            'depreciation.buildingBasis': 'Automatisch berechnete Gebäude-AfA-Basis aus Gebäudeanteil und AfA-relevanten Nebenkosten.',
            'depreciation.buildingBasisOverrideEnabled': 'Nur aktivieren, wenn ein Anbieter oder Steuerberater eine abweichende AfA-Basis vorgibt.',
            'depreciation.degressiveRatePercent': 'Satz der degressiven Gebäude-AfA. Bei Neubau aktuell häufig 5 %, sofern Voraussetzungen erfüllt sind.',
            'depreciation.linearRatePercent': 'Linearer AfA-Satz für die Vergleichs- oder Anschlussrechnung.',
            'depreciation.degressiveActive': 'Aktiviert die degressive AfA statt nur linearer AfA.',
            'depreciation.autoSwitchToLinear': 'Wechselt automatisch zur linearen AfA, sobald diese günstiger ist.',
            'depreciation.special7bActive': 'Aktiviert die Sonder-AfA nach §7b. Förderfähigkeit und Nachweise müssen im Einzelfall geprüft werden.',
            'depreciation.special7bApplicationDate': 'Datum für die Auswahl der §7b-Regel. Vorbelegt aus dem Kaufdatum, falls kein Bauantragsdatum bekannt ist.',
            'depreciation.special7bArea': 'Fläche, mit der die §7b-Förderhöchstgrenze je m² multipliziert wird.',
            'depreciation.special7bConstructionCostLimitPerSqm': 'Baukostenobergrenze je m². Sie entscheidet, ob die Wohnung dem Grunde nach förderfähig sein kann.',
            'depreciation.special7bActualConstructionCostPerSqm': 'Optionaler Prüfwert der tatsächlichen Baukosten je m². Wenn unbekannt, bei 0 lassen.',
            'depreciation.special7bLimitPerSqm': 'Maximale §7b-Bemessungsgrundlage je m². Dieser Wert kappt die Sonder-AfA-Basis.',
            'depreciation.special7bBasis': 'Berechnete §7b-Bemessungsgrundlage nach Kappung. Sie ist readonly, damit Formel und Eingaben konsistent bleiben.',
            'depreciation.special7bRatePercent': 'Jährlicher Sonder-AfA-Satz für die §7b-Bemessungsgrundlage.',
            'depreciation.special7bYears': 'Anzahl der Jahre, in denen die Sonder-AfA angesetzt wird.',
            'depreciation.furnitureBasis': 'Bemessungsgrundlage für separat abschreibbare Möbel oder Küche.',
            'depreciation.furnitureRatePercent': 'AfA-Satz für Möbel/Küche. Diese AfA läuft getrennt von der Gebäude-AfA.',
            'tax.calculationMethod': 'Grenzsteuersatz ist eine Vereinfachung. §32a rechnet mit Einkommensteuertarif, Soli und optional Kirchensteuer.',
            'tax.taxableIncomeBeforeInvestment': 'Zu versteuerndes Einkommen vor der Immobilie. Es ist die Basis für die Steuerwirkung.',
            'tax.taxableIncomeAnnualIncreasePercent': 'Jährliche Steigerung des sonstigen zu versteuernden Einkommens.',
            'tax.marginalTaxRatePercent': 'Vereinfachter Grenzsteuersatz für die schnelle Steuerwirkung, wenn nicht §32a verwendet wird.',
            'tax.assessmentType': 'Einzel- oder Splittingtabelle für die Einkommensteuerberechnung nach §32a.',
            'tax.taxYear': 'Tarifjahr für die Einkommensteuerberechnung.',
            'tax.churchTax': 'Nur aktivieren, wenn Kirchensteuerpflicht besteht. Das Bundesland bestimmt den Satz.',
            'tax.churchTaxState': 'Bundesland für den Kirchensteuersatz, unabhängig vom Objektstandort.',
            'sale.annualValueIncreasePercent': 'Annahme für die Wertentwicklung der Immobilie bis zum Verkaufsjahr.',
            'sale.parkingAnnualValueIncreasePercent': 'Eigene Wertsteigerung für Stellplätze. Standardmäßig wird der Objektsatz übernommen.',
            'sale.includeParkingInSalePrice': 'Steuert, ob Stellplätze im Verkaufspreis bewertet werden.',
            'sale.sellingCostsPercent': 'Verkaufskosten als Prozent vom Verkaufspreis, z. B. Makler oder Abwicklungskosten.',
            'sale.prepaymentPenaltyAmount': 'Optionale Vorfälligkeitsentschädigung im Verkaufsjahr.',
            'sale.taxFreeSale': 'Modellannahme für steuerfreien Verkauf. Für die 10-Jahres-Frist zählt grundsätzlich der notarielle Kaufvertrag.',
            'parking.label': 'Interne Bezeichnung des Stellplatzes, z. B. SP 12.',
            'parking.purchasePrice': 'Kaufpreis des Stellplatzes. Er kann in Kaufpreis, AfA und Verkaufswert einfließen.',
            'parking.monthlyRent': 'Monatliche Nettokaltmiete des Stellplatzes.',
            'parking.buildingSharePercent': 'Gebäudeanteil des Stellplatzes für AfA und Kaufpreisaufteilung.',
            'parking.landSharePercent': 'Nicht abschreibbarer Grundstücksanteil des Stellplatzes.',
            'parking.depreciationMode': 'Legt fest, ob der Stellplatz in der Gebäude-AfA steckt oder separat linear abgeschrieben wird.',
            'parking.depreciationRatePercent': 'Nur bei eigenem linearem Satz editierbar.',
            'parking.includedInPurchasePrice': 'Aktiv, wenn der Stellplatz zum Gesamt-Kaufpreis und Verkaufspreis gehören soll.',
            'constructionInterest.year': 'Jahr, in dem die Bauzeitzinsen anfallen.',
            'constructionInterest.amount': 'Bauzeitzinsen dieses Jahres als Bruttobetrag.',
            'constructionInterest.deductible': 'Steuert nur den steuerlichen Werbungskostenansatz.',
            'constructionInterest.financed': 'Aktiv: kein direkter Cash-Abfluss; die Finanzierung muss im Kreditmodul abgebildet sein.',
            'loan.principal': 'Darlehensbetrag dieses Kreditmoduls.',
            'loan.interestRatePercent': 'Sollzins p.a. für die Zinsberechnung.',
            'loan.initialRepaymentPercent': 'Tilgungssatz zu Beginn, solange keine tilgungsfreie Zeit greift.',
            'loan.interestOnlyYears': 'In dieser Zeit werden nur Zinsen gezahlt; die Tilgung startet danach.',
            'loan.repaymentRateAfterInterestOnlyPercent': 'Tilgungssatz nach Ablauf der tilgungsfreien Zeit.',
            'loan.constantAnnuity': 'Aktiv: die Jahresrate bleibt grundsätzlich konstant und der Tilgungsanteil steigt mit sinkender Restschuld.',
        };
    }

    createSwitchNotes()
    {
        return {
            'acquisitionCosts.financingCostsDeductible': 'Steuert nur die steuerliche Behandlung von Pfandrecht/Kreditkosten.',
            'depreciation.buildingBasisOverrideEnabled': 'Nur nutzen, wenn eine externe AfA-Basis vorliegt.',
            'depreciation.special7bActive': 'Förderfähigkeit und Nachweis separat prüfen.',
            'sale.taxFreeSale': '10-Jahres-Frist nach notariellem Kaufvertrag prüfen.',
            'constructionInterest.deductible': 'Steuert nur die steuerliche Behandlung.',
            'constructionInterest.financed': 'Darlehenssumme muss die Finanzierung abbilden.',
        };
    }

    enhanceFieldGuidance()
    {
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => this.enhanceInputGuidance(input, input.dataset.path));
    }

    enhanceInputGuidance(input, key)
    {
        const container = input.closest('label') || input.closest('[class*="col-"]') || input.parentElement;
        if(!container || container.dataset.guidanceReady === key) {
            return;
        }

        const anchor = container.matches('label') ? container : container.querySelector('label');
        if(anchor) {
            if(!container.querySelector('.rei-info-button')) {
                this.appendFieldHelp(anchor, key, input);
            }
            this.appendAutoStatus(anchor, input);
            this.appendSwitchNote(container, key, input);
        }
        this.appendFieldMeta(container, input);
        container.dataset.guidanceReady = key;
    }

    appendSwitchNote(container, key, input)
    {
        const text = this.switchNotes[key];
        if(!text || input?.type !== 'checkbox' || container.querySelector(`[data-switch-note="${key}"]`)) {
            return;
        }

        const note = document.createElement('small');
        note.className = 'rei-switch-note';
        note.dataset.switchNote = key;
        note.textContent = text;
        container.append(note);
    }

    appendFieldHelp(container, key, input = null)
    {
        const text = this.fieldHelpTexts[key];
        if(!text || container.querySelector(`[data-help-button="${key}"]`) || container.querySelector('.rei-info-button')) {
            return;
        }

        const button = this.createInfoButton(text, `Info zu ${container.textContent.trim() || key}`);
        button.dataset.helpButton = key;
        const before = input || container.querySelector('input, select');
        if(before && before.parentElement === container && !['checkbox', 'radio'].includes(before.type)) {
            container.insertBefore(button, before);
        } else {
            container.append(button);
        }
        this.initPopover(button);
    }

    appendAutoStatus(container, input)
    {
        const path = input?.dataset?.path;
        if(!this.autoManagedPaths.has(path) || container.querySelector(`[data-auto-status="${path}"]`)) {
            return;
        }

        const badge = document.createElement('span');
        badge.className = 'rei-auto-badge';
        badge.dataset.autoStatus = path;
        badge.textContent = 'auto';

        const reset = document.createElement('button');
        reset.className = 'rei-reset-auto';
        reset.type = 'button';
        reset.dataset.resetAuto = path;
        reset.textContent = 'zurück auf auto';
        reset.addEventListener('click', event => {
            event.preventDefault();
            this.resetAutoField(path);
        });

        const before = input || container.querySelector('input, select');
        if(before && before.parentElement === container) {
            container.insertBefore(badge, before);
            container.insertBefore(reset, before);
        } else {
            container.append(badge, reset);
        }
    }

    appendFieldMeta(container, input)
    {
        const path = input?.dataset?.path;
        if(!path || ['hidden', 'checkbox', 'radio'].includes(input.type) || container.querySelector(`[data-meta-for="${path}"]`)) {
            return;
        }

        const meta = document.createElement('small');
        meta.className = 'rei-field-meta';
        meta.dataset.metaFor = path;
        input.insertAdjacentElement('afterend', meta);
    }

    createInfoButton(text, label)
    {
        const button = document.createElement('button');
        button.className = 'rei-info-button';
        button.type = 'button';
        button.dataset.bsToggle = 'popover';
        button.dataset.bsTrigger = 'focus';
        button.dataset.bsPlacement = 'top';
        button.dataset.bsContent = text;
        button.setAttribute('aria-label', label);
        button.innerHTML = '<i class="bi bi-info-circle"></i>';
        return button;
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
        this.addParkingRow({label: 'Stellplatz', purchasePrice: 20000, monthlyRent: 80, buildingSharePercent: 80, landSharePercent: 20, depreciationMode: 'building_basis', includedInPurchasePrice: true});
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
            ['depreciationMode', 'AfA-Typ', 'segment', data.depreciationMode || 'building_basis', [
                ['building_basis', 'in Gebäude-Basis'],
                ['linear_building', 'linearer Gebäudesatz'],
                ['custom_linear', 'eigener linearer Satz'],
            ]],
            ['depreciationRatePercent', 'AfA %', 'number', data.depreciationRatePercent ?? 5.26],
            ['depreciationStartYear', 'AfA Startjahr', 'number', data.depreciationStartYear || defaultStartYear],
            ['depreciationStartMonth', 'AfA Startmonat', 'number', data.depreciationStartMonth || defaultStartMonth],
            ['includedInPurchasePrice', 'im Kaufpreis enthalten', 'checkbox', data.includedInPurchasePrice ?? true],
        ]);
        this.parkingList.append(row);
        this.updateRepeaterNames(this.parkingList, 'parkingUnits');
        this.updateParkingDepreciationRateState(row);
        this.syncSpecial7bCalculatedCosts();
        this.updateGuidanceState();
        this.saveDraft();
    }

    addConstructionInterestRow(data = {})
    {
        const row = this.row('constructionInterest', [
            ['label', 'Bezeichnung', 'text', data.label || 'Bauzeitzinsen'],
            ['year', 'Jahr', 'number', data.year || Number(this.value('property.purchaseYear') || 2026)],
            ['amount', 'Betrag', 'number', data.amount || 0],
            ['deductible', 'als Werbungskosten ansetzen', 'checkbox', data.deductible ?? true],
            ['financed', 'mitfinanziert', 'checkbox', data.financed ?? false],
        ]);
        this.constructionInterestList.append(row);
        this.updateRepeaterNames(this.constructionInterestList, 'constructionInterest_yearlyEntries');
        this.updateGuidanceState();
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
            ['constantAnnuity', 'Annuität konstant halten', 'checkbox', data.constantAnnuity ?? true],
            ['redeemOnSale', 'bei Verkauf ablösen', 'checkbox', data.redeemOnSale ?? true],
        ]);
        this.loanList.append(row);
        this.updateRepeaterNames(this.loanList, 'loans');
        this.updateGuidanceState();
        this.saveDraft();
    }

    row(type, fields)
    {
        const row = document.createElement('div');
        row.className = 'rei-repeater-row';
        row.dataset.type = type;
        fields.forEach(([field, label, inputType, value, options]) => {
            const wrapper = document.createElement(inputType === 'segment' || inputType === 'checkbox' ? 'div' : 'label');
            if(inputType !== 'checkbox') {
                wrapper.textContent = label;
            }
            wrapper.dataset.field = field;
            if(inputType === 'checkbox') {
                wrapper.className = 'rei-check-field form-check form-switch';
            }
            if(type === 'parking' && field === 'depreciationMode') {
                wrapper.title = 'in Gebäude-Basis: läuft mit der Gebäude-AfA. Linearer Gebäudesatz: separate AfA mit linearem Objektsatz. Eigener linearer Satz: separate AfA mit eigenem Prozentsatz.';
            }
            if(inputType === 'segment') {
                wrapper.className = 'rei-repeater-field';
                this.appendFieldHelp(wrapper, `${type}.${field}`);
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
                input.id = this.nextControlId(`${type}-${field}`);
                const checkboxLabel = document.createElement('label');
                checkboxLabel.className = 'form-check-label';
                checkboxLabel.htmlFor = input.id;
                checkboxLabel.textContent = label;
                wrapper.append(input, checkboxLabel);
                this.appendFieldHelp(wrapper, `${type}.${field}`, input);
                this.appendSwitchNote(wrapper, `${type}.${field}`, input);
                row.append(wrapper);
                return;
            } else {
                input.value = value;
            }
            wrapper.append(input);
            this.appendFieldHelp(wrapper, `${type}.${field}`, input);
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
            this.updateGuidanceState();
            this.saveDraft();
            this.calculate();
        });
        row.append(remove);
        return row;
    }

    nextControlId(prefix)
    {
        this.controlId += 1;
        return `rei-${prefix}-${this.controlId}`.replace(/[^a-zA-Z0-9_-]/g, '-');
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
        const mode = row.querySelector('input[type="radio"][data-field="depreciationMode"]:checked')?.value || 'building_basis';
        const rate = row.querySelector('[data-field="depreciationRatePercent"]');
        if(rate) {
            rate.disabled = mode !== 'custom_linear';
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

    markAutoFieldManualFromEvent(input)
    {
        const path = input?.dataset?.path;
        if(!path || !this.autoManagedPaths.has(path) || input.readOnly || input.type === 'hidden') {
            return;
        }
        input.dataset.autoValue = 'false';
    }

    updateGuidanceState()
    {
        this.updateInlineFieldMeta();
        this.updateAutoStatusBadges();
    }

    resetAutoField(path)
    {
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        if(input) {
            input.dataset.autoValue = 'true';
        }

        if(['depreciation.startYear', 'depreciation.startMonth'].includes(path)) {
            this.syncDepreciationStartDefaults(true);
        } else if(path === 'depreciation.buildingBasis') {
            const override = this.getRootElement().querySelector('[data-role="building-basis-override"]');
            if(override) {
                override.checked = false;
            }
            this.updateBuildingBasisOverrideState();
        } else if(path === 'depreciation.special7bApplicationDate') {
            this.syncSpecial7bApplicationDate(true);
            this.syncSpecial7bRuleDefaults(false);
        } else if(path === 'depreciation.special7bArea') {
            this.syncSpecial7bArea(true);
        } else if(['depreciation.special7bConstructionCostLimitPerSqm', 'depreciation.special7bLimitPerSqm', 'depreciation.special7bRatePercent', 'depreciation.special7bYears'].includes(path)) {
            this.syncSpecial7bRuleDefaults(true);
        } else if(path === 'sale.parkingAnnualValueIncreasePercent') {
            this.syncParkingValueIncreaseDefault(true);
        }

        this.syncSpecial7bCalculatedCosts();
        this.updateGuidanceState();
        this.saveDraft();
        this.calculate();
    }

    updateInlineFieldMeta()
    {
        const values = this.currentInputOverview();
        const metas = {
            'property.livingArea': values.livingArea > 0 && values.apartment > 0
                ? `Kaufpreis Wohnung je m²: ${this.eur(values.apartment / values.livingArea)}`
                : '',
            'property.apartmentPurchasePrice': `Kaufpreis inkl. Stellplätze/Küche/Sonstiges: ${this.eur(values.purchaseTotal)}`,
            'property.otherPurchasePrice': values.other > 0 ? `wird mit Grund/Gebäude aufgeteilt: ${this.eur(values.other)}` : 'optional; 0 €, wenn nicht separat vorhanden',
            'property.furniturePurchasePrice': values.furniture > 0 ? `separate Möbel/Küche-Basis: ${this.eur(values.furniture)}` : 'bei 0 keine separate Möbel/Küche-AfA',
            'property.landSharePercent': `Grundstückskosten: ${this.eur(values.landCosts)}`,
            'property.buildingSharePercent': `Herstellungskosten/Gebäude: ${this.eur(values.buildingCosts)}`,
            'rent.apartmentMonthlyRent': `Wohnung p.a.: ${this.eur(values.apartmentAnnualRent)} · inkl. Stellplätze p.a.: ${this.eur(values.totalAnnualRent)}`,
            'rent.annualIncreasePercent': `erste Steigerung: ${this.percentInput(values.rentIncrease)} alle ${Math.max(values.rentIncreaseEveryYears, 1)} Jahr(e)`,
            'rent.increaseEveryYears': `Mietsteigerung wird alle ${Math.max(values.rentIncreaseEveryYears, 1)} Jahr(e) angewendet`,
            'rent.vacancyPercent': `Abschlag auf Jahresmiete: ${this.eur(values.totalAnnualRent * values.vacancyRate)}`,
            'expenses.nonRecoverableHausgeldMonthly': `laufende Ausgaben gesamt p.a.: ${this.eur(values.expenseAnnualTotal)}`,
            'expenses.maintenanceReserveMonthly': `p.a.: ${this.eur(values.maintenanceMonthly * 12)}`,
            'expenses.managementMonthly': `p.a.: ${this.eur(values.managementMonthly * 12)}`,
            'expenses.servicePoolMonthly': `p.a.: ${this.eur(values.serviceMonthly * 12)}`,
            'expenses.furnitureReserveMonthly': `p.a.: ${this.eur(values.furnitureReserveMonthly * 12)}`,
            'expenses.otherMonthlyCosts': `p.a.: ${this.eur(values.otherMonthlyCosts * 12)}`,
            'expenses.annualIncreasePercent': `Kostensteigerung: ${this.percentInput(values.expenseIncrease)} alle ${Math.max(values.expenseIncreaseEveryYears, 1)} Jahr(e)`,
            'acquisitionCosts.realEstateTransferTaxPercent': `${this.eur(values.acquisitionBase)} × ${this.percentInput(values.rettPercent)} = ${this.eur(values.rett)}`,
            'acquisitionCosts.notaryPercent': `${this.eur(values.acquisitionBase)} × ${this.percentInput(values.notaryPercent)} = ${this.eur(values.notary)}`,
            'acquisitionCosts.landRegisterPurchasePercent': `${this.eur(values.acquisitionBase)} × ${this.percentInput(values.landRegisterPercent)} = ${this.eur(values.landRegister)}`,
            'acquisitionCosts.landRegisterLienPercent': `${this.eur(values.loanPrincipalTotal)} × ${this.percentInput(values.lienPercent)} = ${this.eur(values.lien)}`,
            'acquisitionCosts.brokerPercent': `${this.eur(values.acquisitionBase)} × ${this.percentInput(values.brokerPercent)} = ${this.eur(values.broker)}`,
            'acquisitionCosts.otherAcquisitionCosts': `sonstige Erwerbsnebenkosten: ${this.eur(values.otherAcquisitionCosts)}`,
            'acquisitionCosts.otherFinancingCosts': `sonstige Kreditkosten: ${this.eur(values.otherFinancingCosts)}`,
            'acquisitionCosts.notaryLandRegisterTaxTreatment': values.notaryImmediateDeductible
                ? 'Notar/Grundbuch werden im Kaufjahr als Werbungskosten angesetzt'
                : 'Notar/Grundbuch erhöhen anteilig die AfA-Basis',
            'acquisitionCosts.financingCostsDeductible': values.financingCostsDeductible
                ? 'Pfandrecht/Kreditkosten werden steuerlich als Werbungskosten berücksichtigt'
                : 'Pfandrecht/Kreditkosten werden nicht als Werbungskosten angesetzt',
            'settings.initialEquityAmount': values.financingGapText,
            'settings.autoSpecialRepaymentMode': values.autoRepaymentText,
            'settings.discountRatePercent': `wirkt auf NPV/FV mit ${this.percentInput(values.discountRate)}; nominale Kennzahlen bleiben unverändert`,
            'depreciation.startYear': `Auto-Quelle: Fertigstellung ${values.completionYear || '-'} / ${values.completionMonth || '-'}`,
            'depreciation.startMonth': `normale AfA wird im Startjahr monatsgenau gerechnet`,
            'depreciation.buildingBasis': `automatisch berechnet: ${this.eur(values.buildingDepreciationBasis)}`,
            'depreciation.degressiveRatePercent': `volle Jahres-AfA grob: ${this.eur(values.buildingDepreciationBasis * values.degressiveRate / 100)}`,
            'depreciation.linearRatePercent': `lineare Jahres-AfA grob: ${this.eur(values.buildingDepreciationBasis * values.linearRate / 100)}`,
            'depreciation.special7bApplicationDate': `Regelwerte werden aus diesem Datum vorbelegt`,
            'depreciation.special7bArea': `${this.dec(values.special7bArea, 2)} m² × ${this.eur(values.special7bLimitPerSqm)} = ${this.eur(values.special7bCap)}`,
            'depreciation.special7bConstructionCostLimitPerSqm': `${this.dec(values.special7bArea, 2)} m² × ${this.eur(values.special7bConstructionLimit)} = ${this.eur(values.special7bConstructionCap)}`,
            'depreciation.special7bActualConstructionCostPerSqm': values.special7bActualCostsPerSqm > 0
                ? `Prüfwert: ${this.eur(values.special7bActualCostsPerSqm)} / m²`
                : 'optional; leer/0, wenn kein Anbieter-Prüfwert vorliegt',
            'depreciation.special7bLimitPerSqm': `${this.dec(values.special7bArea, 2)} m² × ${this.eur(values.special7bLimitPerSqm)} = ${this.eur(values.special7bCap)}`,
            'depreciation.special7bBasis': `min(${this.eur(values.buildingDepreciationBasis)}, ${this.eur(values.special7bCap)}) = ${this.eur(values.special7bAssessmentBasis)}`,
            'depreciation.special7bRatePercent': `Sonder-AfA p.a.: ${this.eur(values.special7bAssessmentBasis * values.special7bRate / 100)}`,
            'depreciation.special7bYears': `Sonder-AfA gesamt: ${this.eur(values.special7bAssessmentBasis * values.special7bRate / 100 * values.special7bYears)}`,
            'depreciation.furnitureBasis': `Möbel/Küche-AfA p.a.: ${this.eur(values.furnitureBasis * values.furnitureRate / 100)}`,
            'depreciation.furnitureRatePercent': `Möbel/Küche-AfA p.a.: ${this.eur(values.furnitureBasis * values.furnitureRate / 100)}`,
            'tax.calculationMethod': values.taxMethod === 'section_32a' ? 'rechnet mit Tarif, Soli und optional Kirchensteuer' : 'nutzt steuerlichen Gewinn/Verlust × Grenzsteuersatz',
            'tax.taxableIncomeBeforeInvestment': `Startwert zvE: ${this.eur(values.taxableIncome)}`,
            'tax.taxableIncomeAnnualIncreasePercent': `zvE-Steigerung: ${this.percentInput(values.taxableIncomeIncrease)} p.a.`,
            'tax.marginalTaxRatePercent': values.taxMethod === 'section_32a' ? 'im §32a-Modus deaktiviert' : `vereinfachter Satz: ${this.percentInput(values.marginalTaxRate)}`,
            'tax.churchTax': values.churchTax ? 'Kirchensteuer wird in Steuer vor/nach Investition einbezogen' : 'Kirchensteuer wird nicht berücksichtigt',
            'sale.annualValueIncreasePercent': values.saleText,
            'sale.parkingAnnualValueIncreasePercent': `Stellplätze im Verkauf: ${values.includeParkingInSale ? this.percentInput(values.parkingValueIncrease) : 'deaktiviert'}`,
            'sale.includeParkingInSalePrice': values.includeParkingInSale ? 'Stellplätze erhöhen den Verkaufspreis' : 'Stellplätze werden im Verkaufspreis nicht bewertet',
            'sale.sellingCostsPercent': `${this.percentInput(values.sellingCostsPercent)} vom Verkaufspreis = ${this.eur(values.salePrice * values.sellingCostsPercent / 100)}`,
            'sale.prepaymentPenaltyAmount': `Abzug im Verkaufsjahr: ${this.eur(values.prepaymentPenalty)}`,
            'sale.taxFreeSale': values.taxFreeSale ? 'Verkauf wird im Modell steuerfrei behandelt' : 'Verkauf wird nicht als steuerfrei markiert',
        };

        this.getRootElement().querySelectorAll('[data-meta-for]').forEach(meta => {
            const text = metas[meta.dataset.metaFor] || '';
            meta.textContent = text;
            meta.hidden = text === '';
        });
    }

    currentInputOverview()
    {
        const apartment = Number(this.value('property.apartmentPurchasePrice') || 0);
        const other = Number(this.value('property.otherPurchasePrice') || 0);
        const furniture = Number(this.value('property.furniturePurchasePrice') || 0);
        const livingArea = Number(this.value('property.livingArea') || 0);
        const landShare = Number(this.value('property.landSharePercent') || 0) / 100;
        const buildingShare = Number(this.value('property.buildingSharePercent') || 0) / 100;
        const parkingRows = this.collectRows(this.parkingList);
        const includedParking = parkingRows.filter(row => row.includedInPurchasePrice);
        const parkingPurchase = includedParking.reduce((total, row) => total + Number(row.purchasePrice || 0), 0);
        const parkingRent = parkingRows.reduce((total, row) => total + Number(row.monthlyRent || 0), 0);
        const propertySplitBase = apartment + other;
        const acquisitionBase = propertySplitBase + parkingPurchase;
        const loanPrincipalTotal = this.collectRows(this.loanList).reduce((total, loan) => total + Number(loan.principal || 0), 0);
        const constructionInterestTotal = this.collectRows(this.constructionInterestList).reduce((total, row) => total + Number(row.amount || 0), 0);
        const purchaseTotal = acquisitionBase + furniture;
        const taxTreatment = this.value('acquisitionCosts.notaryLandRegisterTaxTreatment');
        const notaryImmediateDeductible = taxTreatment === 'immediate_deductible';
        const special7bArea = Number(this.value('depreciation.special7bArea') || this.value('property.livingArea') || 0);
        const special7bLimitPerSqm = Number(this.value('depreciation.special7bLimitPerSqm') || 0);
        const special7bConstructionLimit = Number(this.value('depreciation.special7bConstructionCostLimitPerSqm') || 0);
        const special7bAssessmentBasis = this.calculatedSpecial7bAssessmentBasis();
        const saleYears = Math.max(Number(this.value('property.saleYear') || 0) - Number(this.value('property.purchaseYear') || 0), 0);
        const valueIncrease = Number(this.value('sale.annualValueIncreasePercent') || 0);
        const includeParkingInSale = Boolean(this.value('sale.includeParkingInSalePrice'));
        const parkingValueIncrease = Number(this.value('sale.parkingAnnualValueIncreasePercent') || valueIncrease || 0);
        const propertySaleBase = propertySplitBase + furniture;
        const salePriceFallback = propertySaleBase * Math.pow(1 + valueIncrease / 100, saleYears)
            + (includeParkingInSale ? parkingPurchase * Math.pow(1 + parkingValueIncrease / 100, saleYears) : 0);
        const salePrice = Number(this.lastResultSummary.salePrice || 0) || salePriceFallback;
        const totalInvestmentCost = Number(this.lastResultSummary.totalInvestmentCost || this.lastResultSummary.totalCosts || 0);
        const financingGap = totalInvestmentCost > 0 ? loanPrincipalTotal - totalInvestmentCost : 0;

        return {
            apartment,
            other,
            furniture,
            livingArea,
            completionYear: Number(this.value('property.completionYear') || 0),
            completionMonth: Number(this.value('property.completionMonth') || 0),
            propertySplitBase,
            parkingPurchase,
            purchaseTotal,
            acquisitionBase,
            landCosts: propertySplitBase * landShare,
            buildingCosts: propertySplitBase * buildingShare,
            apartmentAnnualRent: Number(this.value('rent.apartmentMonthlyRent') || 0) * 12,
            totalAnnualRent: (Number(this.value('rent.apartmentMonthlyRent') || 0) + parkingRent) * 12,
            vacancyRate: Number(this.value('rent.vacancyPercent') || 0) / 100,
            rentIncrease: Number(this.value('rent.annualIncreasePercent') || 0),
            rentIncreaseEveryYears: Number(this.value('rent.increaseEveryYears') || 1),
            maintenanceMonthly: Number(this.value('expenses.maintenanceReserveMonthly') || 0),
            managementMonthly: Number(this.value('expenses.managementMonthly') || 0),
            serviceMonthly: Number(this.value('expenses.servicePoolMonthly') || 0),
            furnitureReserveMonthly: Number(this.value('expenses.furnitureReserveMonthly') || 0),
            otherMonthlyCosts: Number(this.value('expenses.otherMonthlyCosts') || 0),
            expenseAnnualTotal: (
                Number(this.value('expenses.nonRecoverableHausgeldMonthly') || 0)
                + Number(this.value('expenses.maintenanceReserveMonthly') || 0)
                + Number(this.value('expenses.managementMonthly') || 0)
                + Number(this.value('expenses.servicePoolMonthly') || 0)
                + Number(this.value('expenses.furnitureReserveMonthly') || 0)
                + Number(this.value('expenses.otherMonthlyCosts') || 0)
            ) * 12,
            expenseIncrease: Number(this.value('expenses.annualIncreasePercent') || 0),
            expenseIncreaseEveryYears: Number(this.value('expenses.increaseEveryYears') || 1),
            rettPercent: Number(this.value('acquisitionCosts.realEstateTransferTaxPercent') || 0),
            notaryPercent: Number(this.value('acquisitionCosts.notaryPercent') || 0),
            landRegisterPercent: Number(this.value('acquisitionCosts.landRegisterPurchasePercent') || 0),
            lienPercent: Number(this.value('acquisitionCosts.landRegisterLienPercent') || 0),
            brokerPercent: Number(this.value('acquisitionCosts.brokerPercent') || 0),
            otherAcquisitionCosts: Number(this.value('acquisitionCosts.otherAcquisitionCosts') || 0),
            otherFinancingCosts: Number(this.value('acquisitionCosts.otherFinancingCosts') || 0),
            rett: acquisitionBase * Number(this.value('acquisitionCosts.realEstateTransferTaxPercent') || 0) / 100,
            notary: acquisitionBase * Number(this.value('acquisitionCosts.notaryPercent') || 0) / 100,
            landRegister: acquisitionBase * Number(this.value('acquisitionCosts.landRegisterPurchasePercent') || 0) / 100,
            lien: loanPrincipalTotal * Number(this.value('acquisitionCosts.landRegisterLienPercent') || 0) / 100,
            broker: acquisitionBase * Number(this.value('acquisitionCosts.brokerPercent') || 0) / 100,
            notaryImmediateDeductible,
            financingCostsDeductible: Boolean(this.value('acquisitionCosts.financingCostsDeductible')),
            loanPrincipalTotal,
            constructionInterestTotal,
            financingGapText: totalInvestmentCost > 0
                ? `Darlehen ${this.eur(loanPrincipalTotal)} · Gesamtaufwand ${this.eur(totalInvestmentCost)} · Differenz ${this.eur(financingGap)}`
                : `Darlehenssumme aktuell: ${this.eur(loanPrincipalTotal)}`,
            autoRepaymentText: this.value('settings.autoSpecialRepaymentMode') === 'positive_cashflow_after_tax'
                ? 'positive Cashflows werden zur Sondertilgung genutzt'
                : 'positive Cashflows bleiben frei verfügbar',
            discountRate: Number(this.value('settings.discountRatePercent') || 0),
            buildingDepreciationBasis: this.calculatedBuildingDepreciationBasis(),
            degressiveRate: Number(this.value('depreciation.degressiveRatePercent') || 0),
            linearRate: Number(this.value('depreciation.linearRatePercent') || 0),
            special7bArea,
            special7bLimitPerSqm,
            special7bConstructionLimit,
            special7bActualCostsPerSqm: Number(this.value('depreciation.special7bActualConstructionCostPerSqm') || 0),
            special7bCap: special7bArea * special7bLimitPerSqm,
            special7bConstructionCap: special7bArea * special7bConstructionLimit,
            special7bAssessmentBasis,
            special7bRate: Number(this.value('depreciation.special7bRatePercent') || 0),
            special7bYears: Number(this.value('depreciation.special7bYears') || 0),
            furnitureBasis: Number(this.value('depreciation.furnitureBasis') || 0),
            furnitureRate: Number(this.value('depreciation.furnitureRatePercent') || 0),
            taxMethod: this.value('tax.calculationMethod'),
            taxableIncome: Number(this.value('tax.taxableIncomeBeforeInvestment') || 0),
            taxableIncomeIncrease: Number(this.value('tax.taxableIncomeAnnualIncreasePercent') || 0),
            marginalTaxRate: Number(this.value('tax.marginalTaxRatePercent') || 0),
            churchTax: Boolean(this.value('tax.churchTax')),
            salePrice,
            saleText: `${this.eur(propertySaleBase + (includeParkingInSale ? parkingPurchase : 0))} × Wertsteigerung über ${saleYears} Jahre ≈ ${this.eur(salePrice)}`,
            includeParkingInSale,
            parkingValueIncrease,
            sellingCostsPercent: Number(this.value('sale.sellingCostsPercent') || 0),
            prepaymentPenalty: Number(this.value('sale.prepaymentPenaltyAmount') || 0),
            taxFreeSale: Boolean(this.value('sale.taxFreeSale')),
        };
    }

    updateAutoStatusBadges()
    {
        this.getRootElement().querySelectorAll('[data-auto-status]').forEach(badge => {
            const path = badge.dataset.autoStatus;
            const automatic = this.isAutoFieldCurrentlyAutomatic(path);
            badge.textContent = automatic ? 'auto' : 'manuell';
            badge.classList.toggle('rei-auto-badge--manual', !automatic);
            const reset = this.getRootElement().querySelector(`[data-reset-auto="${path}"]`);
            if(reset) {
                reset.hidden = automatic || path === 'depreciation.special7bBasis';
            }
        });
    }

    isAutoFieldCurrentlyAutomatic(path)
    {
        if(path === 'depreciation.buildingBasis') {
            return !this.buildingBasisOverrideEnabled();
        }
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        if(!input) {
            return true;
        }
        if(input.dataset.autoValue === 'true' || input.readOnly) {
            return true;
        }
        if(input.dataset.autoValue === 'false') {
            return false;
        }
        const expected = this.expectedAutoValue(path);
        return expected !== null && Math.abs(Number(input.value || 0) - expected) < 0.01;
    }

    expectedAutoValue(path)
    {
        if(path === 'depreciation.startYear') {
            return Number(this.value('property.completionYear') || 0);
        }
        if(path === 'depreciation.startMonth') {
            return Number(this.value('property.completionMonth') || 0);
        }
        if(path === 'depreciation.special7bArea') {
            return Number(this.value('property.livingArea') || 0);
        }
        if(path === 'sale.parkingAnnualValueIncreasePercent') {
            return Number(this.value('sale.annualValueIncreasePercent') || 0);
        }
        if(path === 'depreciation.special7bBasis') {
            return this.calculatedSpecial7bAssessmentBasis();
        }
        if(['depreciation.special7bConstructionCostLimitPerSqm', 'depreciation.special7bLimitPerSqm', 'depreciation.special7bRatePercent', 'depreciation.special7bYears'].includes(path)) {
            const date = String(this.value('depreciation.special7bApplicationDate') || '2023-01-01');
            const rule = this.special7bRules.find(item => date >= item.from && date < item.until) || this.special7bRules[1];
            return {
                'depreciation.special7bConstructionCostLimitPerSqm': rule.constructionCostLimitPerSqm,
                'depreciation.special7bLimitPerSqm': rule.specialAfaLimitPerSqm,
                'depreciation.special7bRatePercent': rule.ratePercent,
                'depreciation.special7bYears': rule.years,
            }[path];
        }
        return null;
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

    syncBuildingBasisFromEvent(input)
    {
        if(input?.dataset?.path === 'depreciation.buildingBasisOverrideEnabled') {
            this.updateBuildingBasisOverrideState();
            return;
        }
        this.syncBuildingBasisInput();
    }

    updateBuildingBasisOverrideState()
    {
        const input = this.getRootElement().querySelector('[data-role="building-basis-input"]');
        if(!input) {
            return;
        }

        const automatic = !this.buildingBasisOverrideEnabled();
        input.readOnly = automatic;
        this.setFieldContainerDisabled(input, automatic);
        this.syncBuildingBasisInput();
    }

    syncBuildingBasisInput()
    {
        const input = this.getRootElement().querySelector('[data-role="building-basis-input"]');
        if(!input || this.buildingBasisOverrideEnabled()) {
            return;
        }
        input.value = this.numberValue(this.calculatedAutomaticBuildingDepreciationBasis());
    }

    buildingBasisOverrideEnabled()
    {
        return Boolean(this.getRootElement().querySelector('[data-role="building-basis-override"]')?.checked);
    }

    syncParkingValueIncreaseDefaultFromEvent(input)
    {
        if(!input?.matches?.('[data-path]')) {
            return;
        }
        if(input.dataset.path === 'sale.parkingAnnualValueIncreasePercent') {
            input.dataset.autoValue = 'false';
            return;
        }
        if(input.dataset.path === 'sale.annualValueIncreasePercent') {
            this.syncParkingValueIncreaseDefault(false);
        }
    }

    syncParkingValueIncreaseDefault(force)
    {
        this.setAutoNumberDefault('sale.parkingAnnualValueIncreasePercent', Number(this.value('sale.annualValueIncreasePercent') || 0), force, true);
    }

    setAutoNumberDefault(path, value, force, allowZero = false)
    {
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        if(!input || !Number.isFinite(value) || value < 0 || (!allowZero && value === 0)) {
            return;
        }
        if(force || input.value === '' || input.dataset.autoValue === 'true' || (!allowZero && Number(input.value || 0) <= 0)) {
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
            areaInput.dataset.autoValue = 'true';
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
        if(this.buildingBasisOverrideEnabled()) {
            const manualBuildingBasis = Number(this.value('depreciation.buildingBasis') || 0);
            if(manualBuildingBasis > 0) {
                return manualBuildingBasis;
            }
        }

        return this.calculatedAutomaticBuildingDepreciationBasis();
    }

    calculatedAutomaticBuildingDepreciationBasis()
    {
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
        if(input && (force || input.value === '' || Number(input.value || 0) <= 0 || input.dataset.autoValue === 'true')) {
            input.value = String(value);
            input.dataset.autoValue = 'true';
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
        conservative.sale.parkingAnnualValueIncreasePercent = Math.max(Number(base.sale.parkingAnnualValueIncreasePercent ?? base.sale.annualValueIncreasePercent ?? 0) - 1, 0);
        conservative.rent.annualIncreasePercent = Math.max(Number(base.rent.annualIncreasePercent || 0) - 1, 0);
        conservative.rent.vacancyPercent = Number(base.rent.vacancyPercent || 0) + 3;
        conservative.expenses.annualIncreasePercent = Number(base.expenses.annualIncreasePercent || 0) + 1;

        const optimistic = this.clone(base);
        optimistic.scenarioName = 'optimistisch';
        optimistic.sale.annualValueIncreasePercent = Number(base.sale.annualValueIncreasePercent || 0) + 1;
        optimistic.sale.parkingAnnualValueIncreasePercent = Number(base.sale.parkingAnnualValueIncreasePercent ?? base.sale.annualValueIncreasePercent ?? 0) + 1;
        optimistic.rent.annualIncreasePercent = Number(base.rent.annualIncreasePercent || 0) + 1;
        optimistic.rent.vacancyPercent = Math.max(Number(base.rent.vacancyPercent || 0) - 1, 0);

        const response = await this.request('compareScenarios', {scenarios: [base, conservative, optimistic]}, {method: 'POST'});
        this.setComparisonCollapsed(false);
        await this.comparisonTable.setData(response.data);
        this.comparisonTable.redraw(true);
    }

    collectScenario()
    {
        this.updateBuildingBasisOverrideState();
        this.syncSpecial7bCalculatedCosts();
        const scenario = {};
        this.getRootElement().querySelectorAll('[data-path]').forEach(input => this.setPath(scenario, input.dataset.path, this.inputValue(input)));
        scenario.parkingUnits = this.collectRows(this.parkingList);
        scenario.constructionInterest ??= {};
        scenario.constructionInterest.yearlyEntries = this.collectRows(this.constructionInterestList);
        scenario.loans = this.collectRows(this.loanList).map((loan, index) => ({...loan, priority: index + 1}));
        scenario.depreciation ??= {};
        if(!scenario.depreciation.buildingBasisOverrideEnabled) {
            scenario.depreciation.buildingBasis = 0;
        }
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
            this.addParkingRow({label: 'Stellplatz', buildingSharePercent: 80, landSharePercent: 20, depreciationMode: 'building_basis', includedInPurchasePrice: true});
        }

        this.constructionInterestList.innerHTML = '';
        (scenario.constructionInterest?.yearlyEntries || []).forEach(row => this.addConstructionInterestRow(row));

        this.loanList.innerHTML = '';
        (scenario.loans || []).forEach(row => this.addLoanRow(row));
        if(this.getPath(scenario, 'depreciation.startYear') === undefined && this.getPath(scenario, 'depreciation.startMonth') === undefined) {
            this.syncDepreciationStartDefaults(true);
        }
        this.syncSpecial7bDefaults(false);
        this.updateBuildingBasisOverrideState();
        this.normalizeIntegerInputs();
        this.updateTaxModeState();
        this.updateDependentFieldStates();
        this.syncParkingValueIncreaseDefault(this.getPath(scenario, 'sale.parkingAnnualValueIncreasePercent') === undefined);
        this.restoringDraft = false;
        this.updateGuidanceState();
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
        this.updateBuildingBasisOverrideState();
        this.normalizeIntegerInputs();
        this.updateTaxModeState();
        this.updateDependentFieldStates();
        this.restoringDraft = false;
        this.updateGuidanceState();
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

    updateDependentFieldStates()
    {
        const special7bActive = Boolean(this.value('depreciation.special7bActive'));
        [
            'depreciation.special7bApplicationDate',
            'depreciation.special7bArea',
            'depreciation.special7bConstructionCostLimitPerSqm',
            'depreciation.special7bActualConstructionCostPerSqm',
            'depreciation.special7bLimitPerSqm',
            'depreciation.special7bRatePercent',
            'depreciation.special7bYears',
        ].forEach(path => this.setPathDisabled(path, !special7bActive));
        this.setPathDisabled('depreciation.special7bBasis', !special7bActive, true);
        this.setRoleDisabled('special7b-costs-per-sqm', !special7bActive, true);

        const churchTaxActive = Boolean(this.value('tax.churchTax'));
        this.setPathDisabled('tax.churchTaxState', !churchTaxActive);
    }

    setPathDisabled(path, disabled, keepReadonly = false)
    {
        const input = this.getRootElement().querySelector(`[data-path="${path}"]`);
        if(!input) {
            return;
        }
        if(!keepReadonly) {
            input.disabled = disabled;
        }
        this.setFieldContainerDisabled(input, disabled);
    }

    setRoleDisabled(role, disabled, keepReadonly = false)
    {
        const input = this.getRootElement().querySelector(`[data-role="${role}"]`);
        if(!input) {
            return;
        }
        if(!keepReadonly) {
            input.disabled = disabled;
        }
        this.setFieldContainerDisabled(input, disabled);
    }

    setFieldContainerDisabled(input, disabled)
    {
        const container = input.closest('label') || input.closest('[class*="col-"]') || input.parentElement;
        container?.classList.toggle('rei-field-disabled', disabled);
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
            'depreciation.buildingBasis': 0,
            'depreciation.buildingBasisOverrideEnabled': false,
            'depreciation.degressiveRatePercent': 5,
            'depreciation.linearRatePercent': 3,
            'depreciation.degressiveActive': true,
            'depreciation.autoSwitchToLinear': true,
            'depreciation.special7bConstructionCostLimitPerSqm': 5200,
            'depreciation.special7bLimitPerSqm': 4000,
            'depreciation.special7bRatePercent': 5,
            'depreciation.special7bYears': 4,
            'depreciation.special7bReducesBookValueImmediately': false,
            'depreciation.furnitureRatePercent': 10,
            'tax.calculationMethod': 'marginal_rate',
            'tax.assessmentType': 'single',
            'tax.taxYear': 2026,
            'tax.churchTax': false,
            'tax.churchTaxState': 'BY',
            'tax.taxableIncomeAnnualIncreasePercent': 0,
            'tax.marginalTaxRatePercent': 42,
            'sale.annualValueIncreasePercent': 2,
            'sale.parkingAnnualValueIncreasePercent': 2,
            'sale.includeParkingInSalePrice': true,
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
        if(input.tagName.toLowerCase() === 'select') {
            const stringValue = String(value ?? '');
            input.value = Array.from(input.options).some(option => option.value === stringValue)
                ? stringValue
                : Array.from(input.options).find(option => option.defaultSelected)?.value || input.options[0]?.value || '';
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
        this.lastResultSummary = result.summary || {};
        this.renderWarnings(result.warnings || []);
        this.renderSummary(result.summary || {}, result.scales || {});
        this.renderSidebarCostBreakdown(result.summary || {});
        this.renderSpecial7bInputs(result.summary?.special7b || {});
        this.updateGuidanceState();
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
        this.renderSaleBreakdown(summary);
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

    renderSaleBreakdown(summary)
    {
        const sale = summary.salePriceBreakdown || {};
        const rows = [
            ['Objektwert im Verkaufsjahr', sale.propertySalePrice],
            [sale.includeParkingInSalePrice === false ? 'Stellplatzwert im Verkaufsjahr (deaktiviert)' : 'Stellplatzwert im Verkaufsjahr', sale.parkingSalePrice],
            ['Verkaufspreis gesamt', summary.salePrice, true],
        ];
        this.saleBreakdown.innerHTML = this.renderBreakdownRows(rows);
    }

    renderDepreciationBasisBreakdown(summary)
    {
        const special = summary.special7b || {};
        const parkingRateLabel = summary.parkingDepreciationMixedRates
            ? 'gemischt'
            : (Number(summary.parkingDepreciationRate || 0) > 0 ? this.pct(summary.parkingDepreciationRate) : '');
        const parkingBasisLabel = parkingRateLabel ? `separate SP-AfA-Basis (${parkingRateLabel})` : 'separate SP-AfA-Basis';
        const rows = [
            [summary.buildingBasisOverrideEnabled ? 'Gebäude-Basis manuell' : 'Gebäude-Basis automatisch', summary.objectBuildingDepreciationBasis],
            ['Stellplätze in Gebäude-AfA', summary.parkingIncludedInBuildingDepreciationBasis],
            ['Gebäude-AfA-Basis gesamt', summary.buildingDepreciationBasis, true],
            ['rechnerischer Stellplatzanteil aus Kaufpreisaufteilung', summary.parkingDepreciationShareInCostAllocation],
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

    percentInput(value)
    {
        return `${this.dec(Number(value || 0), 2)} %`;
    }

    escape(value)
    {
        return String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char]));
    }
}

Weblication.registerClass(GUI_InvestmentCalculator);
