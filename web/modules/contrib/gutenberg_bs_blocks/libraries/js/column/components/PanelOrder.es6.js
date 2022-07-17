import breakpoints, { labels } from "breakpoints.es6";
import ColumnOrderRangeControl from "./ColumnOrderRangeControl.es6";

const { PanelBody } = wp.components;

const PanelOrder = ({ attributes, setAttributes }) => {
  return (
    <PanelBody title={Drupal.t("Column order")} initialOpen={false}>
      {breakpoints.map((breakpoint, i) => (
        <>
          <ColumnOrderRangeControl
            label={labels[i]}
            attributeName={`order${breakpoint}`}
            value={attributes[`order${breakpoint}`]}
            setAttributes={setAttributes}
          />
          <hr />
        </>
      ))}
    </PanelBody>
  );
};

export default PanelOrder;
