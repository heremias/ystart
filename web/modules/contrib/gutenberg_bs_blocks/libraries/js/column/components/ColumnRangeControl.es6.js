const { RangeControl } = wp.components;

const ColumnRangeControl = ({ attributeName, setAttributes, ...props }) => {
  return (
    <RangeControl
      onChange={selectedValue => {
        setAttributes({
          [attributeName]: selectedValue
        });
      }}
      {...props}
    />
  );
};

export default ColumnRangeControl;
