async function test() {
  const headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
    "Origin": "https://laysha.safetalkai.my.id"
  };

  try {
    const res = await fetch("https://backend.safetalkai.my.id/api/chat/send", {
      method: "POST",
      headers,
      body: JSON.stringify({ message: "Hello" })
    });
    console.log("Status:", res.status);
    console.log("Headers:");
    res.headers.forEach((value, name) => console.log(name, ":", value));
    const text = await res.text();
    console.log("Body:", text);
  } catch (err) {
    console.error(err);
  }
}

test();
