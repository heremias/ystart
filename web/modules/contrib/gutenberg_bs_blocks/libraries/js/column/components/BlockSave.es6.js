import cx from "classnames";
import breakpoints, { getInfix } from "breakpoints.es6";

// Fallback to 'wp.editor' for backwards compatibility
const { InnerBlocks } = wp.blockEditor || wp.editor;

const BlockSave = ({ className, attributes }) => {
  const { alignment, sizeXs, sizeSm, sizeMd, sizeLg, sizeXl } = attributes;

  const classes = breakpoints.reduce(
    (accumulator, breakpoint) => {
      const infix = getInfix(breakpoint);
      const size = attributes[`size${breakpoint}`];
      const order = attributes[`order${breakpoint}`];
      const offset = attributes[`offset${breakpoint}`];

      if (typeof size !== "undefined") {
        accumulator[`col${infix}`] = breakpoint !== "Xs" && size === 0;
        accumulator[`col${infix}-${size}`] = size > 0;
      }

      if (typeof order !== "undefined") {
        accumulator[`order${infix}-first`] = order === -1;
        accumulator[`order${infix}-last`] = order === 13;
        accumulator[`order${infix}-${order}`] = order !== -1 && order !== 13;
      }

      if (typeof order !== "undefined") {
        accumulator[`offset${infix}-${offset}`] = offset > 0;
      }

      return accumulator;
    },
    {
      col: [sizeXs, sizeSm, sizeMd, sizeLg, sizeXl].every(
        size => typeof size === "undefined"
      ),
      "align-self-start": alignment && alignment === "top",
      "align-self-center": alignment && alignment === "center",
      "align-self-end": alignment && alignment === "bottom"
    }
  );

  return (
    <div className={cx(classes, className)}>
      <InnerBlocks.Content />
    </div>
  );
};

export default BlockSave;
