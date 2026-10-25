# realestateinvestment

Real estate investment calculator for leveraged residential property scenarios in Germany.

This is a POOL framework application for calculating and evaluating residential real estate investment scenarios with financing, taxes, depreciation, cash flows, NPV, future value, and efficiency metrics.

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
