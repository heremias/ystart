import breakpoints, { labels } from "breakpoints.es6";
import ColumnOffsetRangeControl from "./ColumnOffsetRangeControl.es6";

const { PanelBody } = wp.components;

const PanelOffset = ({ attributes, setAttributes }) => {
  return (
    <PanelBody title={Drupal.t("Column offset")} initialOpen={false}>
      {breakpoints.map((breakpoint, i) => (
        <>
          <ColumnOffsetRangeControl
            label={labels[i]}
            attributeName={`offset${breakpoint}`}
            value={attributes[`offset${breakpoint}`]}
            setAttributes={setAttributes}
          />
          <hr />
        </>
      ))}
    </PanelBody>
  );
};

export default PanelOffset;
