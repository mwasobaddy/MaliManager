# Product tours: driver.js

Decided: onboarding walkthroughs and feature tours are built on
[driver.js](https://driverjs.com) (`npm i driver.js`), vanilla JS, MIT
license, no dependencies.

## Why driver.js

- Inertia remounts the React tree on every visit, so the tour driver
  must live above the page components. driver.js is framework-agnostic
  (selectors + popover definitions), while react-joyride couples to the
  component tree and fights the router.
- ~5KB. shepherd.js is heavier for the same job; intro.js needs a
  commercial license.

## Integration shape (when implemented)

- `driver({ showProgress: true, steps: [...] })` + `drive()`, with the
  `driver.js/dist/driver.css` import kept in one place.
- One `useProductTour(tourId, steps)` hook above the page layer, keyed
  off the Inertia component name (e.g. dashboard, properties index).
- Anchors are `data-tour="..."` attributes, never CSS-class or
  nth-child selectors (those rot with every redesign).
- Completion flags in `localStorage` (`tour.<id>.done`); auto-start on
  first visit to a toured page; never auto-start under test
  (`import.meta.env.MODE === 'test'` skips).
