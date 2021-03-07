return <>
  <!doctype html>
  <html class="no-js" lang={$lang ?? 'en'}>
    <Head title={$title ?? null} />
    <Body>
      {$children ?? null}
    </Body>
  </html>
</>;
