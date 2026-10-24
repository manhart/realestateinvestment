const assert = require('node:assert/strict');
const {test} = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

let Calculator;
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../guis/GUI_InvestmentCalculator/GUI_InvestmentCalculator.js'), 'utf8'), {
    GUI_Module: class {},
    Weblication: {registerClass(value) {Calculator = value;}},
    window: {setTimeout, clearTimeout},
});

function calculator() {
    const gui = new Calculator();
    gui.calculateSequence = 0;
    gui.calculationRevision = 0;
    gui.lastCalculationFingerprint = null;
    gui.data = {scenarioName: 'Basis', scenarioGroup: 'A', rent: {apartmentMonthlyRent: 500}};
    gui.collectScenario = () => gui.data;
    gui.getRootElement = () => ({querySelectorAll: () => []});
    gui.rendered = [];
    gui.render = value => gui.rendered.push(value);
    gui.setCalculationStatus = (text, state) => gui.status = state;
    return gui;
}

test('metadata changes do not duplicate numerical requests', async () => {
    const gui = calculator();
    let requests = 0;
    gui.request = async () => {requests++; return {warnings: []};};
    await gui.calculate();
    gui.data.scenarioName = 'Umbenannt';
    gui.data.scenarioGroup = 'B';
    await gui.calculate();
    assert.equal(requests, 1);
    assert.equal(gui.status, 'current');
});

test('input followed by change sends only one calculation', async () => {
    const gui = calculator();
    let requests = 0;
    gui.request = async () => {requests++; return {warnings: []};};
    gui.scheduleCalculate();
    gui.scheduleCalculate(true);
    await new Promise(resolve => setImmediate(resolve));
    gui.scheduleCalculate(true);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests, 1);
    assert.equal(gui.status, 'current');
});

test('an old response cannot render during the new input debounce', async () => {
    const gui = calculator();
    const pending = [];
    gui.request = () => new Promise(resolve => pending.push(resolve));
    const first = gui.calculate();
    gui.data.rent.apartmentMonthlyRent = 600;
    gui.scheduleCalculate();
    pending[0]({value: 'old', warnings: []});
    await first;
    assert.equal(gui.rendered.length, 0);
    assert.equal(gui.status, 'busy');
    const second = gui.calculate();
    pending[1]({value: 'new', warnings: []});
    await second;
    assert.equal(gui.rendered[0].value, 'new');
    assert.equal(gui.status, 'current');
});

test('reverting input while a request is pending does not leave a stale busy state', async () => {
    const gui = calculator();
    const pending = [];
    gui.request = () => new Promise(resolve => pending.push(resolve));
    const first = gui.calculate();
    gui.data.rent.apartmentMonthlyRent = 600;
    gui.scheduleCalculate();
    gui.data.rent.apartmentMonthlyRent = 500;
    const second = gui.calculate();
    pending[1]({value: 'current', warnings: []});
    await second;
    pending[0]({value: 'superseded', warnings: []});
    await first;
    assert.equal(gui.rendered.length, 1);
    assert.equal(gui.rendered[0].value, 'current');
    assert.equal(gui.status, 'current');
});

test('computed readonly fields do not mark a scenario dirty', () => {
    const gui = calculator();
    gui.data.depreciation = {special7bBasis: 1000};
    gui.getRootElement = () => ({querySelectorAll: () => [{dataset: {path: 'depreciation.special7bBasis'}}]});
    gui.savedSnapshot = gui.scenarioFingerprint(gui.data);
    gui.data.depreciation.special7bBasis = 900;
    assert.equal(gui.hasUnsavedChanges(), false);
    gui.data.rent.apartmentMonthlyRent = 600;
    assert.equal(gui.hasUnsavedChanges(), true);
});

test('unassigned legacy drafts remain unsaved even if their values match a baseline', () => {
    const gui = calculator();
    gui.baselineSnapshot = gui.scenarioFingerprint(gui.data);
    gui.restoredUnsaved = true;
    assert.equal(gui.hasUnsavedChanges(), true);
});
