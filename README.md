# realestateinvestment

Real estate investment calculator for leveraged residential property scenarios in Germany.

This is a POOL framework application for calculating and evaluating residential real estate investment scenarios with financing, taxes, depreciation, cash flows, NPV, future value, and efficiency metrics.

## Version and Changelog

The small version button in the app header opens the change history.
[CHANGELOG.md](CHANGELOG.md) is the single source for its German release
notes. Pending changes stay under `Unreleased`; numbered entries summarize
documented versions, not individual commits.

The app version is defined by `RealEstateInvestmentApp::VERSION` and passed
to POOL's application configuration. For a version update, update that
constant and move the relevant pending notes into a matching dated entry.
Use patch versions for fixes and minor versions for new features. Version
metadata does not change scenario or browser-draft formats.

## Local path

- App path: `/virtualweb/manhart/realestateinvestment`
- Browser path: `http://pofolio.local/manhart/realestateinvestment/`

## Tests

```bash
XDEBUG_MODE=off php8.4 /usr/local/bin/phpunit --do-not-record-test-run-history --configuration phpunit.xml
node tests/ToolbarStateTest.cjs
```

## Scenarios

Select a saved scenario and click **Load** to open it. **Save** updates the
active scenario, including changes to its name or group. **Save a copy**
creates a separate variant without modifying the original. Input changes
recalculate automatically; the browser draft is separate from server saves.

Keep your private workspace link safe. Anyone with that link can view and
edit its saved scenarios. The bookmark action explains how to save the link
in your browser; it does not create a bookmark automatically.

## One-Time Financing Costs

Ground-charge notary and registration costs can be entered as a percentage
estimate of total loan principal, one total amount, or separate notary and
land-register amounts. Only the selected mode contributes to costs. Other
fields are disabled but their values remain available after switching modes
or saving and loading a scenario. An explicit zero never activates another
mode. Scenarios without a mode continue to use the percentage estimate.

Purchase-contract notary fees and ownership-transfer registration fees stay
separate from financing costs. Other credit costs, Disagio and construction
interest must not duplicate the entered ground-charge costs. The existing
deduction switch applies to ground-charge and other credit costs in the
purchase year; Disagio retains its own deduction setting and payment year.

Entered loan amounts are never changed automatically. Funds earmarked for
financed construction interest and Disagio are reserved before calculating
the purchase funding gap or free loan surplus. A surplus enters cashflow
once in the purchase year, without changing taxable income, acquisition
costs or initial equity. It is excluded from automatic special repayment.
The annual model does not simulate a separate loan-disbursement account.

## Tax and Depreciation Assumptions

The standard linear building rate follows the completion year. Individual
rates and useful lives require explicit expert mode. Automatic conversion
from declining-balance to straight-line depreciation uses remaining value
and remaining useful life; furniture and separate parking depreciation
stop at their respective cost bases.

Section 7b requires a real application date or an explicitly selected
application period. Eligibility declarations belong to the user; documents
are not uploaded or verified. Open conditions are shown as assumptions,
while known failed conditions exclude the allowance. Acquisition costs
before the cap and the capped allowance basis are separate values.

The Section 23 sale period and Section 7b binding period are independent.
Sale tax, Section 7b recapture and associated interest are not calculated.
Affected end-value metrics are marked provisional separately from their
unchanged value-based rating badges. The selected tax tariff is held
constant for projections;
this calculator is not a complete personal income-tax return.

Legal references: [Section 7 EStG](https://www.gesetze-im-internet.de/estg/__7.html),
[Section 7b EStG](https://www.gesetze-im-internet.de/estg/__7b.html),
[Section 23 EStG](https://www.gesetze-im-internet.de/estg/__23.html), and the
[BMF application letter of 21 May 2025](https://www.bundesfinanzministerium.de/Content/DE/Downloads/BMF_Schreiben/Steuerarten/Einkommensteuer/2025-05-21-anwendungsschreiben-7b-estg-neu.html).
