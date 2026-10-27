// Exclude direct Vectron messages before downloading; forwarding sender is not a supplier criterion.
return $input.all().flatMap((item, index) => {
    const source = item.json;
    const values = [];
    function collect(value) {
        if (Array.isArray(value)) return value.forEach(collect);
        if (value && typeof value === 'object') {
            collect(value.address); collect(value.value); collect(value.text);
        } else if (typeof value === 'string') values.push(value);
    }
    collect(source.envelope?.from ?? source.from ?? source.headers?.from);
    const addresses = values.flatMap(value => value.match(/[A-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[A-Z0-9.-]+/gi) ?? []);
    if (addresses.some(address => address.toLowerCase() === 'noreply@myvectron.com')) return [];
    return [{ ...item, pairedItem: { item: index } }];
});
