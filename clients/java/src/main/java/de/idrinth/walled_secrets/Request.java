package de.idrinth.walled_secrets;

import java.io.IOException;
import java.io.UnsupportedEncodingException;
import java.net.ConnectException;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.KeyManagementException;
import java.security.KeyStoreException;
import java.security.NoSuchAlgorithmException;
import java.time.Instant;
import javax.json.Json;
import javax.json.JsonObject;
import javax.json.JsonReader;
import javax.net.ssl.SSLContext;
import org.apache.commons.io.IOUtils;
import org.apache.http.HttpEntity;
import org.apache.http.HttpResponse;
import org.apache.http.client.config.RequestConfig;
import org.apache.http.client.methods.HttpPost;
import org.apache.http.entity.BasicHttpEntity;
import org.apache.http.impl.client.CloseableHttpClient;
import org.apache.http.impl.client.HttpClientBuilder;

public class Request {
    private final SSLContext sslContext;
    private final Config config;

    public Request(TrustManager manager, Config config) throws KeyManagementException, NoSuchAlgorithmException, KeyStoreException {
        this.config = config;
        sslContext = org.apache.http.ssl.SSLContextBuilder.create().loadTrustMaterial(
            manager.getKeyStore(),
            manager
        ).build();
    }
    private String get(String key, String value) throws UnsupportedEncodingException
    {
        return key + "=" + URLEncoder.encode(value, StandardCharsets.UTF_8.toString());
    }

    public JsonObject getSecretList(Instant last) throws IOException {
        BasicHttpEntity entity = new BasicHttpEntity();
        entity.setContent(IOUtils.toInputStream(
            get("email", config.getEmail()) + "&" + get("apikey", config.getApikey()),
            StandardCharsets.UTF_8
        ));
        HttpPost post = new HttpPost(config.getServer() + "/api/list-secrets");
        if (last.getEpochSecond() > 0) {
            long updated = last.getEpochSecond() * 1000;
            post.setHeader("X-LAST-UPDATED", String.valueOf(updated));
        }
        return executionHandler(
            post,
            entity
        );
    }

    public JsonObject getLogin(String id, String master) throws IOException {
        BasicHttpEntity entity = new BasicHttpEntity();
        entity.setContent(IOUtils.toInputStream(
            get("master", master) + "&" + get("email", config.getEmail()) + "&" + get("apikey", config.getApikey()),
            StandardCharsets.UTF_8
        ));
        return executionHandler(
            new HttpPost(config.getServer() + "/api/logins/" + id),
            entity
        );
    }

    public JsonObject getNote(String id, String master) throws IOException {
        BasicHttpEntity entity = new BasicHttpEntity();
        entity.setContent(IOUtils.toInputStream(
            get("master", master) + "&" + get("email", config.getEmail()) + "&" + get("apikey", config.getApikey()),
            StandardCharsets.UTF_8
        ));
        return executionHandler(
            new HttpPost(config.getServer() + "/api/notes/" + id),
            entity
        );
    }
    private JsonObject executionHandler(HttpPost uri, HttpEntity entity) throws IOException {
        uri.setEntity(entity);
        uri.setConfig(RequestConfig.DEFAULT);
        uri.setHeader("User-Agent", "Walled-Secrets/1.x");
        uri.setHeader("Cache-Control", "no-cache");
        uri.setHeader("Content-Type", "application/x-www-form-urlencoded");
        CloseableHttpClient client = HttpClientBuilder.create()
                .useSystemProperties()
                .setSSLContext(sslContext)
                .build();
        HttpResponse response = client.execute(uri);
        if (response.getStatusLine().getStatusCode() < 200 || response.getStatusLine().getStatusCode() > 299) {
            throw new ConnectException(response.getStatusLine().getReasonPhrase());
        }
        try (JsonReader reader = Json.createReader(response.getEntity().getContent())) {
            JsonObject data = reader.readObject();
            client.close();
            return data;
        }
    }
}