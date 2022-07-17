import breakpoints, { getInfix } from "breakpoints.es6";

const getEditWrapperProps = attributes => {
  const {
    className,
    alignment,
    sizeXs,
    sizeSm,
    sizeMd,
    sizeLg,
    sizeXl
  } = attributes;

  return breakpoints.reduce(
    (accumulator, breakpoint) => {
      const infix = getInfix(breakpoint);
      const size = attributes[`size${breakpoint}`];
      const order = attributes[`order${breakpoint}`];
      const offset = attributes[`offset${breakpoint}`];

      if (typeof size !== "undefined" && breakpoint !== "Xs") {
        accumulator[`data-size${infix}`] = size;
      }
      if (typeof order !== "undefined") {
        accumulator[`data-order${infix}`] = order;
      }
      if (typeof offset !== "undefined" && offset > 0) {
        accumulator[`data-offset${infix}`] = offset;
      }

      return accumulator;
    },
    {
      className,
      "data-alignment": alignment,
      "data-size": [sizeXs, sizeSm, sizeMd, sizeLg, sizeXl].every(
        size => typeof size === "undefined"
      )
        ? 0
        : sizeXs
    }
  );
};

export default getEditWrapperProps;
