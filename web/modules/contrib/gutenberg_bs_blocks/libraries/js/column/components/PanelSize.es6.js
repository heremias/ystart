import breakpoints, { labels } from "breakpoints.es6";
import ColumnSizeRangeControl from "./ColumnSizeRangeControl.es6";

const { PanelBody } = wp.components;

const PanelSize = ({ attributes, setAttributes }) => {
  return (
    <PanelBody title={Drupal.t("Column size")} initialOpen={false}>
      {breakpoints.map((breakpoint, i) => (
        <>
          <ColumnSizeRangeControl
            label={labels[i]}
            attributeName={`size${breakpoint}`}
            value={attributes[`size${breakpoint}`]}
            setAttributes={setAttributes}
          />
          <hr />
        </>
      ))}
    </PanelBody>
  );
};

export default PanelSize;
