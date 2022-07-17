import PanelSize from "column/components/PanelSize.es6";
import PanelOrder from "column/components/PanelOrder.es6";
import PanelOffset from "column/components/PanelOffset.es6";

// Fallback to 'wp.editor' for backwards compatibility
const { InspectorControls } = wp.blockEditor || wp.editor;

const BlockSidebar = ({ attributes, setAttributes }) => {
  return (
    <InspectorControls>
      <PanelSize attributes={attributes} setAttributes={setAttributes} />
      <PanelOrder attributes={attributes} setAttributes={setAttributes} />
      <PanelOffset attributes={attributes} setAttributes={setAttributes} />
    </InspectorControls>
  );
};

export default BlockSidebar;
